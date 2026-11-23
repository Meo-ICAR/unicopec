<?php

namespace App\Services\Mail;

use App\Models\IncomingEmail;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Unico\Core\Documents\InvoiceReader;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;

/**
 * Confronta l'importo di una fattura allegata con il totale del proforma indicato nell'oggetto dell'email («Proforma #927 - NOME - Totale: € 13,890.84»).
 * L'AI legge l'allegato (PDF o immagine, già archiviato tra i documenti del produttore) e restituisce gli importi che vi trova; il confronto
 * lo fa il codice, al centesimo, con ciascuno degli importi letti (imponibile, totale documento, netto a pagare): un compenso si può fatturare
 * con o senza IVA e ritenuta. Il contenuto del documento è un dato, mai un'istruzione.
 */
class InvoiceProformaChecker
{
    public const COINCIDE = 'coincide';

    public const DIFFERENT = 'differenza';

    public const UNDETERMINED = 'non_determinabile';

    private const MAX_BYTES = 8 * 1024 * 1024;

    /** @return array{esito: string, proforma: ?float, fatture: array<int, array<string, mixed>>, dettaglio: string} */
    public function check(IncomingEmail $email): array
    {
        if ($email->isProviderNotification()) {
            return ['esito' => self::UNDETERMINED, 'proforma' => null, 'fatture' => [], 'dettaglio' => 'Ricevuta o notifica automatica del gestore PEC: non inviata all\'AI.'];
        }

        $expected = self::proformaTotal((string) $email->subject);
        $invoices = [];

        foreach ($this->documents($email) as $document) {
            try {
                $read = $this->readDocument($document);
            } catch (\Throwable $e) {
                $invoices[] = ['documento' => $document->name, 'errore' => mb_substr($e->getMessage(), 0, 160), 'esito' => self::UNDETERMINED];

                continue;
            }

            $invoices[] = ['documento' => $document->name] + $read + ['esito' => $this->compare($expected, $read)];
        }

        $verdicts = collect($invoices)->where('tipo', '!=', 'nota_di_credito')->pluck('esito');
        $overall = $expected === null || $verdicts->isEmpty()
            ? self::UNDETERMINED
            : ($verdicts->contains(self::COINCIDE) ? self::COINCIDE : ($verdicts->contains(self::DIFFERENT) ? self::DIFFERENT : self::UNDETERMINED));

        return ['esito' => $overall, 'proforma' => $expected, 'fatture' => $invoices, 'dettaglio' => $this->summary($overall, $expected, $invoices)];
    }

    /** Totale del proforma dall'oggetto; i numeri sono nel formato «1,440.00» (migliaia con la virgola) ma si accetta anche «1.440,00». */
    public static function proformaTotal(string $subject): ?float
    {
        if (! preg_match('/Totale:\s*€?\s*([\d.,]+)/iu', $subject, $m)) {
            return null;
        }

        $raw = rtrim($m[1], '.,');
        $decimalComma = (bool) preg_match('/,\d{1,2}$/', $raw) && ! preg_match('/\.\d{1,2}$/', $raw);
        $normalized = $decimalComma ? str_replace(['.', ','], ['', '.'], $raw) : str_replace(',', '', $raw);

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    /** @return iterable<int, Document> le fatture e note di credito archiviate dall'email */
    public function documents(IncomingEmail $email): iterable
    {
        $types = DocumentType::query()->whereIn('code', ['FATTURA_COLLABORATORE', 'NOTA_CREDITO_COLLABORATORE'])->pluck('id');

        return Document::query()->where('documentable_type', 'fornitore')->whereIn('document_type_id', $types)
            ->where('metadata->incoming_email_id', $email->id)->orderBy('id')->get();
    }

    /** Lettura AI di un documento, conservata in `metadata.invoice_read`: lo stesso allegato non si rilegge (e non si paga) due volte. @return array<string, mixed> */
    public function readDocument(Document $document, bool $fresh = false): array
    {
        $cached = $document->metadata['invoice_read'] ?? null;

        if (! $fresh && is_array($cached) && array_key_exists('cedente', $cached) && ($cached['v'] ?? 0) >= 2) {
            return $cached;
        }

        $read = ['v' => 2] + $this->read($document);
        $document->forceFill(['metadata' => array_merge((array) $document->metadata, ['invoice_read' => $read])])->saveQuietly();

        return $read;
    }

    /** @return array<string, mixed> */
    private function read(Document $document): array
    {
        $media = $document->getFirstMedia(Document::COLLECTION);

        if ($media === null || $media->size > self::MAX_BYTES) {
            throw new RuntimeException('File assente o troppo grande.');
        }

        // La lettura è in unico-core: fattura elettronica XML con regole locali, altrimenti i gradini economici (cache, testo già estratto
        // dall'app con OCR, pagine ridotte in visione, modello principale solo se serve).
        return app(InvoiceReader::class)->read(
            (string) $media->mime_type,
            (string) file_get_contents($media->getPath()),
            (string) $media->file_name,
            $media->getCustomProperty('extracted_text') ?: null,
        );
    }

    /** @param array<string, mixed> $read */
    private function compare(?float $expected, array $read): string
    {
        $amounts = array_filter([$read['imponibile'] ?? null, $read['totale'] ?? null, $read['netto_a_pagare'] ?? null], fn ($v) => $v !== null);

        if ($expected === null || $amounts === []) {
            return self::UNDETERMINED;
        }

        foreach ($amounts as $amount) {
            if (abs($amount - $expected) < 0.005) {
                return self::COINCIDE;
            }
        }

        return self::DIFFERENT;
    }

    /** @param array<int, array<string, mixed>> $invoices */
    private function summary(string $overall, ?float $expected, array $invoices): string
    {
        if ($expected === null) {
            return 'Nell\'oggetto dell\'email non c\'è il totale del proforma: confronto impossibile.';
        }

        $parts = collect($invoices)->map(function (array $i) use ($expected): string {
            if (isset($i['errore'])) {
                return "{$i['documento']}: non letta ({$i['errore']})";
            }

            $label = $i['tipo'] === 'nota_di_credito' ? 'nota di credito' : 'fattura';
            $amounts = collect(['imponibile' => 'imponibile', 'totale' => 'totale', 'netto_a_pagare' => 'netto'])->map(fn ($n, $k) => isset($i[$k]) ? "{$n} ".number_format($i[$k], 2, ',', '.') : null)->filter()->implode(', ');
            $diff = isset($i['totale']) ? round($i['totale'] - $expected, 2) : null;
            $delta = $diff === null ? '' : ' (differenza dal totale '.number_format($diff, 2, ',', '.').(abs($diff - 2) < 0.005 ? ': probabile marca da bollo' : '').')';

            return "{$label} {$i['documento']}: {$amounts}".($i['esito'] === self::DIFFERENT ? $delta : '').' → '.$i['esito'];
        })->implode('; ');

        return sprintf('Proforma %s: %s. Esito: %s.', number_format($expected, 2, ',', '.'), $parts ?: 'nessuna fattura allegata', $overall);
    }
}
