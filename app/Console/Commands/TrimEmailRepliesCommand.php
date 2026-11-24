<?php

namespace App\Console\Commands;

use App\Models\IncomingEmail;
use App\Services\Mail\ReplyTrimmer;
use Illuminate\Console\Command;

class TrimEmailRepliesCommand extends Command
{
    protected $signature = 'mail:trim-replies {--dry-run : Mostra senza salvare} {--show= : Id di un\'email di cui mostrare il risultato}';

    protected $description = 'Riduce il corpo delle email già importate all\'ultimo messaggio; il testo completo passa in body_full';

    public function handle(): int
    {
        if ($id = $this->option('show')) {
            $e = IncomingEmail::findOrFail($id);
            $this->line(ReplyTrimmer::latest($e->body_full ?? $e->body_text));

            return self::SUCCESS;
        }

        $done = $same = 0;
        IncomingEmail::query()->whereNull('body_full')->whereNotNull('body_text')->orderBy('id')->each(function (IncomingEmail $e) use (&$done, &$same) {
            $latest = ReplyTrimmer::latest($e->body_text);
            if ($latest === trim((string) $e->body_text)) {
                $same++;

                return;
            }
            $this->line(sprintf('#%d %d → %d caratteri', $e->id, mb_strlen($e->body_text), mb_strlen($latest)));
            if (! $this->option('dry-run')) {
                $e->forceFill(['body_full' => $e->body_text, 'body_text' => $latest])->saveQuietly();
            }
            $done++;
        });

        $this->info("Ridotte {$done}, già brevi {$same}.");

        return self::SUCCESS;
    }
}
