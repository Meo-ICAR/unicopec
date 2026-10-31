<?php

namespace App\Services\Mail;

use App\Models\IncomingEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Unico\Core\Documents\DocumentFile;
use Unico\Core\Documents\DocumentPipeline;
use Unico\Core\Documents\Listeners\AnalyzeUploadedDocument;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;

/**
 * Gli allegati delle email di un produttore (agente o collaboratore: `fornitori` del core) diventano suoi documenti nel core
 * (`documents`, `documentable_type = fornitore`), con il tipo documento scelto dall'AI tra quelli plausibili (fattura, nota di credito,
 * IBAN...) oppure, se non riconosciuto, «PEC / comunicazione istituzionale». Il mittente deve coincidere con email, email privata o PEC del produttore.
 * È idempotente: ogni allegato è riconosciuto da `metadata.source` e non viene copiato due volte. Le immagini piccole (firme, loghi) si scartano.
 */
class ProducerDocuments
{
    /** Codici dei tipi tra cui scegliere; l'ultimo è anche il ripiego. */
    private const TYPE_CODES = ['FATTURA_COLLABORATORE', 'NOTA_CREDITO_COLLABORATORE', 'COORDINATE_BANCARIE', 'DOCUMENTO_IDENTIFICATIVO', 'COMUNICAZIONE_PEC'];

    private const FALLBACK_CODE = 'COMUNICAZIONE_PEC';

    /** Sotto questa dimensione un'immagine è quasi sempre una firma o un logo. */
    private const MIN_IMAGE_BYTES = 40960;

    public function __construct(private DocumentPipeline $pipeline) {}

    public function producerOf(IncomingEmail $email): ?Fornitore
    {
        $from = mb_strtolower(trim((string) $email->from_email));

        if ($from === '') {
            return null;
        }

        return Fornitore::query()
            ->where(fn ($q) => $q->whereRaw('lower(email) = ?', [$from])->orWhereRaw('lower(email_private) = ?', [$from])->orWhereRaw('lower(pec) = ?', [$from]))
            ->orderByDesc('is_active')->first();
    }

    /** @return Collection<int, Document> i documenti creati ora */
    public function store(IncomingEmail $email): Collection
    {
        $producer = $this->producerOf($email);
        $created = new Collection;

        if ($producer === null) {
            return $created;
        }

        $types = DocumentType::query()->whereIn('code', self::TYPE_CODES)->get();

        foreach ($email->getMedia(IncomingEmail::ATTACHMENTS) as $media) {
            if ($this->skip($media) || $this->exists($email, $media)) {
                continue;
            }

            try {
                $created->push($this->copy($email, $producer, $media, $types));
            } catch (\Throwable $e) {
                Log::warning('Allegato non archiviato tra i documenti del produttore', ['incoming_email_id' => $email->id, 'media_id' => $media->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        return $created;
    }

    private function skip(Media $media): bool
    {
        return str_starts_with((string) $media->mime_type, 'image/') && $media->size < self::MIN_IMAGE_BYTES;
    }

    private function exists(IncomingEmail $email, Media $media): bool
    {
        return Document::query()->where('documentable_type', 'fornitore')->where('metadata->source', $this->source($email, $media))->exists();
    }

    private function source(IncomingEmail $email, Media $media): string
    {
        return "incoming_email:{$email->id}:media:{$media->id}";
    }

    /** @param Collection<int, DocumentType> $types */
    private function copy(IncomingEmail $email, Fornitore $producer, Media $media, Collection $types): Document
    {
        $document = Document::query()->create([
            'company_id' => $email->company_id,
            'documentable_type' => 'fornitore',
            'documentable_id' => (string) $producer->getKey(),
            'name' => $media->file_name,
            'status' => DocumentStatus::PENDING,
            'metadata' => ['source' => $this->source($email, $media), 'incoming_email_id' => $email->id, 'subject' => mb_substr((string) $email->subject, 0, 200), 'received_at' => $email->received_at?->toIso8601String()],
        ]);

        // L'analisi parte dopo, una volta sola, con i candidati scelti qui.
        AnalyzeUploadedDocument::suspendedWhile(fn () => $document->addMedia($media->getPath())->preservingOriginal()->usingFileName($media->file_name)->toMediaCollection(Document::COLLECTION));

        $document = $document->fresh();
        $fallback = $types->firstWhere('code', self::FALLBACK_CODE);

        try {
            $result = $this->pipeline->run($document, ['classify' => true, 'candidates' => $types]);
            $document = $result->document->fresh('documentType');
        } catch (\Throwable $e) {
            Log::warning('Classificazione allegato non riuscita', ['document' => $document->getKey(), 'error' => mb_substr($e->getMessage(), 0, 200)]);
        }

        if ($document->document_type_id === null && $fallback !== null) {
            $document->forceFill(['document_type_id' => $fallback->getKey()])->save();
        }

        if ($document->status === DocumentStatus::PENDING) {
            $document->update(['status' => DocumentStatus::UPLOADED]);
        }

        return $document->fresh('documentType');
    }
}
