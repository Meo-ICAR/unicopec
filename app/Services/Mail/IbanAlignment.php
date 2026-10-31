<?php

namespace App\Services\Mail;

use App\Models\IncomingEmail;
use App\Services\Bpm\BpmEvents;
use Illuminate\Support\Collection;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;

/**
 * Allineamento dell'IBAN degli agenti: l'IBAN scritto nelle loro fatture (letto dall'AI, vedi InvoiceProformaChecker) deve coincidere con
 * quello della scheda (`fornitoris.iban`) ed essere stato verificato da una persona (`iban_verified_at`). Finché non è così l'agente è «non allineato»:
 * UnicoBPM lo vede dal controllo `fornitori:iban-non-allineati` e scrive al contabile ogni giorno, con un grado che sale col tempo.
 * Un IBAN che cambia avvia subito un processo con l'evento `fornitore.iban_variato`.
 */
class IbanAlignment
{
    public const MISSING = 'iban_assente';

    public const CHANGED = 'iban_variato';

    public const UNVERIFIED = 'non_verificato';

    /** @return Collection<int, array<string, mixed>> un elemento per ogni agente non allineato */
    public function pending(?int $companyId = null): Collection
    {
        $type = DocumentType::query()->where('code', 'FATTURA_COLLABORATORE')->pluck('id')->first();

        if ($type === null) {
            return new Collection;
        }

        return Document::query()->where('documentable_type', 'fornitore')->where('document_type_id', $type)->orderBy('id')->get()
            ->filter(fn (Document $d) => filled($d->metadata['invoice_read']['cedente']['iban'] ?? null))
            ->groupBy('documentable_id')
            ->map(fn (Collection $documents, $producerId) => $this->state(Fornitore::query()->find($producerId), $documents))
            ->filter(fn ($row) => $row !== null && $row['motivo'] !== null && ($companyId === null || (int) $row['company_id'] === $companyId))
            ->values();
    }

    /** Stato di un agente rispetto alle sue fatture. @param Collection<int, Document> $documents fatture con IBAN, dalla più vecchia @return array<string, mixed>|null */
    public function state(?Fornitore $producer, Collection $documents): ?array
    {
        if ($producer === null || $documents->isEmpty()) {
            return null;
        }

        $latest = $this->iban($documents->last());
        $first = $documents->filter(fn (Document $d) => $this->iban($d) === $latest)->first();
        // Da quando l'agente ha comunicato questo IBAN: la data dell'email, non quella dell'archiviazione.
        $since = filled($first->metadata['received_at'] ?? null) ? \Illuminate\Support\Carbon::parse($first->metadata['received_at']) : $first->created_at;
        $current = $producer->iban ? strtoupper(str_replace(' ', '', $producer->iban)) : null;

        $reason = match (true) {
            $current === null => self::MISSING,
            $current !== $latest => self::CHANGED,
            $producer->iban_verified_at === null => self::UNVERIFIED,
            default => null,
        };

        return [
            'fornitore_id' => $producer->id,
            'nome' => $producer->name,
            'company_id' => $producer->company_id,
            'iban_scheda' => $current,
            'iban_fattura' => $latest,
            'iban_precedenti' => $documents->map(fn (Document $d) => $this->iban($d))->unique()->reject(fn ($i) => $i === $latest)->values()->all(),
            'motivo' => $reason,
            'da' => $since,
            'giorni' => (int) $since->diffInDays(now()),
            'email_id' => $documents->last()->metadata['incoming_email_id'] ?? null,
        ];
    }

    /** Una fattura appena archiviata: se l'IBAN è cambiato rispetto a scheda o fatture precedenti, parte il processo in UnicoBPM. */
    public function afterInvoices(IncomingEmail $email): void
    {
        $producer = app(ProducerDocuments::class)->producerOf($email);

        if ($producer === null) {
            return;
        }

        $checker = app(InvoiceProformaChecker::class);
        $type = DocumentType::query()->where('code', 'FATTURA_COLLABORATORE')->pluck('id')->first();
        $documents = Document::query()->where('documentable_type', 'fornitore')->where('documentable_id', (string) $producer->id)->where('document_type_id', $type)->orderBy('id')->get();

        // Le fatture nuove si leggono ora (una chiamata AI ciascuna, poi restano in cache); le altre sono già lette.
        foreach ($documents as $document) {
            try {
                $checker->readDocument($document);
            } catch (\Throwable) {
                // Un allegato illeggibile non ferma il resto.
            }
        }

        $documents = $documents->fresh()->filter(fn (Document $d) => filled($d->metadata['invoice_read']['cedente']['iban'] ?? null))->values();
        $state = $this->state($producer, $documents);

        if ($state === null || $state['motivo'] === null) {
            return;
        }

        $changed = $state['motivo'] === self::CHANGED || $state['iban_precedenti'] !== [];

        if ($changed && BpmEvents::isConfigured()) {
            BpmEvents::dispatchIbanChanged($state, $email);
        }
    }

    /**
     * L'IBAN di un agente è verificato da una persona: da qui l'agente risulta allineato. Senza `$iban` si conferma quello dell'ultima fattura.
     * Un IBAN non valido non si accetta.
     */
    public function align(Fornitore $producer, ?string $iban = null, ?int $userId = null): Fornitore
    {
        $iban = strtoupper(str_replace(' ', '', (string) $iban));

        if ($iban === '') {
            $row = $this->pending()->firstWhere('fornitore_id', $producer->id);
            $iban = (string) ($row['iban_fattura'] ?? $producer->iban);
        }

        if (! EmailIntentExtractor::validIban($iban)) {
            throw new \InvalidArgumentException('IBAN non valido.');
        }

        $changed = strtoupper((string) $producer->iban) !== $iban;

        $producer->forceFill(array_filter([
            'iban' => $iban,
            'iban_updated_at' => $changed ? now() : null,
        ]) + ['iban_verified_at' => now(), 'iban_verified_by' => $userId])->save();

        return $producer;
    }

    private function iban(Document $document): string
    {
        return strtoupper(str_replace(' ', '', (string) $document->metadata['invoice_read']['cedente']['iban']));
    }
}
