<?php

namespace App\Console\Commands;

use App\Models\IncomingEmail;
use App\Services\Mail\ProducerDocuments;
use Illuminate\Console\Command;

class ProducerDocumentsCommand extends Command
{
    protected $signature = 'mail:producer-documents
        {email? : ID dell\'email (se omesso, tutte quelle con allegati)}
        {--account= : Solo le email di questa casella}
        {--limit=100 : Massimo di email esaminate}';

    protected $description = 'Archivia gli allegati delle email dei produttori tra i loro documenti nel core, con il tipo documento appropriato';

    public function handle(ProducerDocuments $documents): int
    {
        $emails = IncomingEmail::query()
            ->when($this->argument('email'), fn ($q, $id) => $q->whereKey($id))
            ->when($this->option('account'), fn ($q, $id) => $q->where('mail_account_id', $id))
            ->whereHas('media', fn ($q) => $q->where('collection_name', IncomingEmail::ATTACHMENTS))
            ->orderBy('received_at')->limit((int) $this->option('limit'))->get();

        $total = 0;

        foreach ($emails as $email) {
            foreach ($documents->store($email) as $document) {
                $total++;
                $this->line(sprintf('#%d %-34s → %s (%s)', $email->id, mb_substr((string) $document->name, 0, 34), $document->documentType?->name ?? 'senza tipo', $document->documentable_id));
            }
        }

        $this->info("Documenti archiviati: {$total}.");

        return self::SUCCESS;
    }
}
