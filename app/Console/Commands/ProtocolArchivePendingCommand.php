<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveEmail;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use Illuminate\Console\Command;

class ProtocolArchivePendingCommand extends Command
{
    protected $signature = 'protocol:archive-pending {--days= : Giorni di attesa della presa in carico (predefinito: pec.archive.max_wait_days)}';

    protected $description = 'Archivia le email protocollate allo smistamento che nessuno ha preso in carico entro il tempo massimo';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('pec.archive.max_wait_days'));

        $emails = IncomingEmail::query()->where('archive_status', 'da_archiviare')
            ->whereHas('protocols', fn ($q) => $q->where('type', Protocol::SMISTAMENTO)->where('protocolled_at', '<', now()->subDays($days)))
            ->whereDoesntHave('protocols', fn ($q) => $q->where('type', Protocol::PRESA_IN_CARICO))
            ->pluck('id');

        foreach ($emails as $id) {
            ArchiveEmail::dispatch($id);
        }

        $this->info("Messe in archiviazione {$emails->count()} email.");

        return self::SUCCESS;
    }
}
