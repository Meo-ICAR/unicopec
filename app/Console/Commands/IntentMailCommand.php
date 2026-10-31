<?php

namespace App\Console\Commands;

use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Services\Bpm\BpmEvents;
use App\Services\Mail\EmailIntentExtractor;
use App\Services\Mail\InvoiceProformaChecker;
use Illuminate\Console\Command;

class IntentMailCommand extends Command
{
    protected $signature = 'mail:intent
        {email? : ID dell\'email (se omesso, le ultime non ancora lette dall\'AI)}
        {--account= : Solo le email di questa casella}
        {--limit=20 : Massimo di email lette}
        {--payload : Mostra l\'evento email.richiesta che andrebbe a UnicoBPM}
        {--save : Registra l\'esito nella cronologia dell\'email (nessun invio a UnicoBPM)}';

    protected $description = 'Legge le email con l\'AI per capire cosa chiedono (es. rettifica IBAN) e prepara la richiesta per UnicoBPM';

    public function handle(EmailIntentExtractor $extractor, InvoiceProformaChecker $invoices): int
    {
        $emails = IncomingEmail::query()->with(['company', 'mailAccount'])
            ->when($this->argument('email'), fn ($q, $id) => $q->whereKey($id))
            ->when($this->option('account'), fn ($q, $id) => $q->where('mail_account_id', $id))
            ->when(! $this->argument('email'), fn ($q) => $q->where('classification', '!=', 'provider_notification')->orWhereNull('classification'))
            ->orderBy('received_at')->limit((int) $this->option('limit'))->get();

        foreach ($emails as $email) {
            try {
                $result = $extractor->extract($email);
            } catch (\Throwable $e) {
                $this->error("#{$email->id}: ".$e->getMessage());

                continue;
            }

            $this->line(sprintf('#%d %-22s %.2f  %s', $email->id, $result['intent'], $result['confidence'], mb_substr((string) $email->subject, 0, 60)));

            if ($result['intent'] === 'nessuna') {
                continue;
            }

            $this->line('     '.$result['summary']);
            $this->line('     dati: '.json_encode($result['entities'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            // Per le fatture si legge anche l'allegato e si confronta l'importo con il totale del proforma.
            if ($result['intent'] === 'fattura_ricevuta') {
                $result['confronto'] = $invoices->check($email);
                $this->line('     confronto: '.$result['confronto']['dettaglio']);
            }

            if ($result['flags']) {
                $this->warn('     DA VERIFICARE: '.implode(', ', $result['flags']));
            }

            if ($this->option('payload')) {
                $this->line(json_encode(BpmEvents::request($email, $result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            if ($this->option('save')) {
                EmailEvent::record($email, EmailEvent::INTENT, $result['flags'] ? 'warning' : 'ok', sprintf('Richiesta: %s%s', $result['intent'], $result['flags'] ? ' · da verificare: '.implode(', ', $result['flags']) : ''), $result);
            }
        }

        return self::SUCCESS;
    }
}
