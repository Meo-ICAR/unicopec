<?php

namespace App\Console\Commands;

use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Services\Mail\InvoiceProformaChecker;
use Illuminate\Console\Command;

class InvoiceCheckCommand extends Command
{
    protected $signature = 'mail:invoice-check
        {email? : ID dell\'email (se omesso, tutte quelle con una fattura archiviata)}
        {--account= : Solo le email di questa casella}
        {--limit=50 : Massimo di email esaminate}
        {--save : Registra l\'esito nella cronologia dell\'email}';

    protected $description = 'Legge con l\'AI le fatture allegate e le confronta con il totale del proforma indicato nell\'oggetto';

    public function handle(InvoiceProformaChecker $checker): int
    {
        $emails = IncomingEmail::query()
            ->when($this->argument('email'), fn ($q, $id) => $q->whereKey($id))
            ->when($this->option('account'), fn ($q, $id) => $q->where('mail_account_id', $id))
            ->where('subject', 'like', '%Totale:%')
            ->orderBy('received_at')->limit((int) $this->option('limit'))->get();

        foreach ($emails as $email) {
            $result = $checker->check($email);

            if ($result['fatture'] === []) {
                continue;
            }

            $this->line(sprintf('#%d %-18s %s', $email->id, $result['esito'], mb_substr((string) $email->subject, 0, 60)));
            $this->line('     '.$result['dettaglio']);

            if ($this->option('save')) {
                EmailEvent::record($email, EmailEvent::INVOICE_CHECK, $result['esito'] === InvoiceProformaChecker::COINCIDE ? 'ok' : 'warning', 'Confronto fattura/proforma: '.$result['dettaglio'], $result);
            }
        }

        return self::SUCCESS;
    }
}
