<?php

namespace App\Console\Commands;

use App\Enums\EmailCategory;
use App\Models\IncomingEmail;
use App\Services\Mail\EmailClassifier;
use App\Services\Mail\EmailPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClassifyMailCommand extends Command
{
    protected $signature = 'mail:classify
        {--limit=50 : Massimo di email classificate}
        {--reclassify : Riclassifica anche quelle già classificate (le ricevute del gestore PEC restano tali)}
        {--dry-run : Mostra l\'esito senza salvare né comunicare a UnicoBPM}';

    protected $description = 'Classifica le email non ancora classificate (regole certe, poi AI) e comunica a UnicoBPM l\'evento email.ricevuta';

    public function handle(EmailClassifier $classifier, EmailPipeline $pipeline): int
    {
        $emails = IncomingEmail::query()->with(['company', 'mailAccount'])
            ->when(! $this->option('reclassify'), fn ($q) => $q->whereNull('classification'))
            ->where(fn ($q) => $q->whereNull('classification')->orWhere('classification', '!=', EmailCategory::ProviderNotification->value))
            ->orderBy('received_at')->limit((int) $this->option('limit'))->get();

        $done = $failed = 0;

        foreach ($emails as $email) {
            try {
                $result = $classifier->classify($email);
            } catch (\Throwable $e) {
                $failed++;
                $this->error("#{$email->id}: ".$e->getMessage());
                Log::warning('mail:classify fallito', ['incoming_email_id' => $email->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);

                continue;
            }

            $this->line(sprintf('#%d %-26s %-8s %s  %s', $email->id, $result->category->value, $result->urgency, $result->source === 'rules' ? '[regole]' : sprintf('[ai %.2f]', $result->confidence), mb_substr((string) $email->subject, 0, 55)));
            $this->line('     '.$result->summary);

            if ($this->option('dry-run')) {
                continue;
            }

            $pipeline->classified($email, $result);
            $done++;
        }

        $this->info("Classificate {$done}, errori {$failed}".($this->option('dry-run') ? ' (dry-run: nulla salvato)' : '').'.');

        return $failed > 0 && $done === 0 && ! $this->option('dry-run') ? self::FAILURE : self::SUCCESS;
    }
}
