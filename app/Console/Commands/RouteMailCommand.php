<?php

namespace App\Console\Commands;

use App\Enums\EmailCategory;
use App\Models\IncomingEmail;
use App\Services\Bpm\BpmEvents;
use App\Services\Mail\EmailRouter;
use Illuminate\Console\Command;

class RouteMailCommand extends Command
{
    protected $signature = 'mail:route
        {--limit=50 : Massimo di email smistate}
        {--reassign : Riassegna anche le email che hanno già un referente}
        {--dry-run : Mostra a chi andrebbero senza salvare né comunicare a UnicoBPM}';

    protected $description = 'Assegna le email classificate al referente giusto dell\'organigramma e comunica a UnicoBPM l\'evento email.assegnata';

    public function handle(EmailRouter $router): int
    {
        $routable = collect(EmailCategory::cases())->filter->isRoutable()->map->value->all();

        $emails = IncomingEmail::query()->with(['company', 'mailAccount'])
            ->whereIn('classification', $routable)
            ->when(! $this->option('reassign'), fn ($q) => $q->whereNull('related_id'))
            ->orderBy('received_at')->limit((int) $this->option('limit'))->get();

        $assigned = $missing = 0;

        foreach ($emails as $email) {
            $category = EmailCategory::from($email->classification);
            $found = $router->route($email, $category);

            if (! $found) {
                $missing++;
                $this->warn("#{$email->id} {$category->value}: nessun referente trovato.");

                continue;
            }

            $this->line(sprintf('#%d %-24s → %-26s (%s; %s)', $email->id, $category->value, $found['employee']->name, $found['role'], $found['via']));

            if ($this->option('dry-run')) {
                continue;
            }

            $email->forceFill(['related_type' => 'employee', 'related_id' => $found['employee']->id])->save();
            BpmEvents::dispatchAssigned($email, $found['employee'], $found['role']);
            $assigned++;
        }

        $this->info("Assegnate {$assigned}, senza referente {$missing}".($this->option('dry-run') ? ' (dry-run: nulla salvato)' : '').'.');

        return self::SUCCESS;
    }
}
