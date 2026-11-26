<?php

namespace App\Console\Commands;

use App\Models\IncomingEmail;
use App\Models\SenderBlacklist;
use Illuminate\Console\Command;

class PurgeBlacklistedMailCommand extends Command
{
    protected $signature = 'mail:purge-blacklisted
        {--dry-run : Mostra le email senza eliminarle}';

    protected $description = 'Elimina (soft delete) le email già importate di mittenti o con oggetti in lista nera';

    public function handle(): int
    {
        $total = 0;

        foreach (IncomingEmail::query()->select('company_id')->distinct()->pluck('company_id') as $companyId) {
            $patterns = SenderBlacklist::patternsFor($companyId);
            $subjects = SenderBlacklist::patternsFor($companyId, SenderBlacklist::SUBJECT);

            IncomingEmail::query()->where('company_id', $companyId)->each(function (IncomingEmail $email) use ($patterns, $subjects, &$total) {
                if (! SenderBlacklist::matches($patterns, $email->from_email) && ! SenderBlacklist::matchesSubject($subjects, $email->subject)) {
                    return;
                }

                $total++;
                $this->line(sprintf('#%d %-40s %s', $email->id, mb_substr((string) $email->from_email, 0, 40), mb_substr((string) $email->subject, 0, 50)));

                if (! $this->option('dry-run')) {
                    $email->delete();
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Da eliminare: ' : 'Eliminate: ').$total.'.');

        return self::SUCCESS;
    }
}
