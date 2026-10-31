<?php

namespace App\Console\Commands;

use App\Services\Mail\EmailIntentExtractor;
use App\Services\Mail\FornitoreProfile;
use App\Services\Mail\InvoiceProformaChecker;
use Illuminate\Console\Command;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;

class InvoiceSupplierCommand extends Command
{
    protected $signature = 'mail:invoice-supplier
        {fornitore? : ID del produttore (se omesso, tutti quelli con fatture archiviate)}
        {--apply : Compila i campi vuoti della scheda del produttore (non sovrascrive mai, l\'IBAN non si scrive)}
        {--limit=50 : Massimo di produttori esaminati}';

    protected $description = 'Legge i dati del cedente dalle fatture dei produttori (IBAN, sede, partita IVA...) e li confronta con la loro scheda';

    public function handle(InvoiceProformaChecker $checker, FornitoreProfile $profile): int
    {
        $type = DocumentType::query()->where('code', 'FATTURA_COLLABORATORE')->pluck('id')->first();

        $groups = Document::query()->where('documentable_type', 'fornitore')->where('document_type_id', $type)
            ->when($this->argument('fornitore'), fn ($q, $id) => $q->where('documentable_id', (string) $id))
            ->orderBy('id')->get()->groupBy('documentable_id')->take((int) $this->option('limit'));

        foreach ($groups as $producerId => $documents) {
            $producer = Fornitore::query()->find($producerId);

            if ($producer === null) {
                continue;
            }

            $reads = $documents->map(function (Document $document) use ($checker) {
                try {
                    return ['documento' => $document->name, 'read' => $checker->readDocument($document)];
                } catch (\Throwable $e) {
                    $this->error("  {$document->name}: ".mb_substr($e->getMessage(), 0, 120));

                    return null;
                }
            })->filter()->values();

            if ($reads->isEmpty()) {
                continue;
            }

            $this->line("<options=bold>#{$producer->id} {$producer->name}</> ({$reads->count()} fatture)");

            $ibans = $reads->map(fn ($r) => strtoupper(preg_replace('/\s+/', '', (string) ($r['read']['cedente']['iban'] ?? ''))))->filter()->unique()->values();

            // Dati più recenti: l'ultima fattura che li riporta.
            $latest = $reads->reverse()->reduce(fn (array $carry, array $r) => $carry + (array) ($r['read']['cedente'] ?? []), []);
            $rows = $profile->compare($producer, $latest);

            // Con IBAN diversi tra le fatture non si sa quale sia quello giusto: non si compila, lo decide una persona.
            if ($ibans->count() > 1) {
                $rows = array_map(fn (array $row) => in_array($row['campo'], ['iban', 'iban_holder'], true) && $row['azione'] === FornitoreProfile::ACTION_FILL ? [...$row, 'azione' => FornitoreProfile::ACTION_CONFLICT] : $row, $rows);
            }

            foreach ($rows as $row) {
                $this->line(sprintf('  %-12s %-13s %-30s %s', $row['colonna'], $row['azione'], $row['attuale'] ?? '—', $row['azione'] === FornitoreProfile::ACTION_SAME ? '' : '← '.$row['fattura']));
            }

            if ($this->option('apply') && ($filled = $profile->fillEmpty($producer, $rows)) !== []) {
                $this->info('  compilati: '.implode(', ', $filled));
            }

            foreach ($ibans as $iban) {
                $this->line(sprintf('  IBAN in fattura %s%s%s', $iban, EmailIntentExtractor::validIban($iban) ? '' : '  [NON VALIDO]', $producer->iban && strtoupper($producer->iban) !== $iban ? '  [DIVERSO da quello in scheda]' : ''));
            }

            if ($producer->iban) {
                $this->line(sprintf('  IBAN in scheda %s — %s', $producer->iban, $producer->iban_verified_at ? 'verificato il '.$producer->iban_verified_at->format('d/m/Y') : 'NON verificato'));
            }

            if ($ibans->count() > 1) {
                $this->warn('  ATTENZIONE: IBAN diversi tra le fatture dello stesso produttore.');
            }
        }

        return self::SUCCESS;
    }
}
