<?php

namespace App\Console\Commands;

use App\Enums\EmailCategory;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Services\Protocol\SmistamentoProtocoller;
use Illuminate\Console\Command;

class ProtocolBackfillCommand extends Command
{
    protected $signature = 'protocol:backfill {--limit=200 : Massimo di email protocollate}';

    protected $description = 'Protocolla (smistamento) le email già assegnate a un referente che non hanno ancora il protocollo';

    public function handle(SmistamentoProtocoller $protocoller): int
    {
        $routable = collect(EmailCategory::cases())->filter->isRoutable()->map->value->all();

        $emails = IncomingEmail::query()
            ->whereIn('classification', $routable)->whereNotNull('related_id')
            ->whereDoesntHave('protocols', fn ($q) => $q->where('type', Protocol::SMISTAMENTO))
            ->orderBy('received_at')->limit((int) $this->option('limit'))->get();

        $done = 0;

        foreach ($emails as $email) {
            if ($protocoller->protocol($email)) {
                $done++;
            }
        }

        $this->info("Protocollate {$done} su {$emails->count()} email.");

        return self::SUCCESS;
    }
}
