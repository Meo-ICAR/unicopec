<?php

namespace App\Console\Commands;

use App\Models\IncomingEmail;
use App\Models\MailAccount;
use App\Services\Mail\PecEnvelope;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class MoveMailByKeywordCommand extends Command
{
    protected $signature = 'mail:move-keyword
        {keyword : Parola da cercare (parola intera, senza distinzione di maiuscole)}
        {folder : Sottocartella di INBOX in cui spostare (creata se manca)}
        {--classification= : Valore da scrivere in incoming_emails.classification}
        {--dry-run : Mostra cosa verrebbe spostato senza spostare}';

    protected $description = 'Sposta sul server le email di tutte le caselle attive che contengono la parola (oggetto o testo, anche dentro la busta PEC)';

    public function handle(PecEnvelope $envelope): int
    {
        $keyword = (string) $this->argument('keyword');
        $pattern = '/\b'.preg_quote($keyword, '/').'\b/iu';

        foreach (MailAccount::where('is_active', true)->whereIn('id', [3, 4])->get() as $account) {
            try {
                $this->process($account, $envelope, $keyword, $pattern);
            } catch (\Throwable $e) {
                $this->error("#{$account->id} {$account->email_address}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function process(MailAccount $account, PecEnvelope $envelope, string $keyword, string $pattern): void
    {
        $client = (new ClientManager)->make([
            'host' => $account->imap_host, 'port' => $account->imap_port,
            'encryption' => $account->imap_encryption, 'validate_cert' => true,
            'username' => $account->imap_username, 'password' => $account->imap_password, 'protocol' => 'imap',
        ]);
        $client->connect();

        $inbox = $client->getFolder('INBOX');
        $target = 'INBOX'.($inbox->delimiter ?: '/').$this->argument('folder');

        // Il server cerca per sottostringa (oggetto o testo): il controllo a parola intera si fa dopo.
        $candidates = $inbox->messages()->subject($keyword)->leaveUnread()->get()
            ->merge($inbox->messages()->text($keyword)->leaveUnread()->get())
            ->unique(fn ($m) => $m->getUid());

        $matched = [];

        foreach ($candidates as $message) {
            $data = $envelope->open($message);
            $haystack = $data['subject'].' '.strip_tags((string) ($data['text'] ?: $data['html']));

            if (preg_match($pattern, $haystack) || preg_match($pattern, (string) $message->getSubject())) {
                $matched[] = [$message, $data];
            }
        }

        $this->info("#{$account->id} {$account->email_address}: {$candidates->count()} candidate, ".count($matched)." con la parola «{$keyword}» → {$target}");

        foreach ($matched as [$message, $data]) {
            $this->line('  '.$message->getDate().' | '.($data['from_email'] ?? '?').' | '.mb_substr((string) $data['subject'], 0, 80));
        }

        if ($this->option('dry-run') || $matched === []) {
            return;
        }

        if (! $client->getFolder($target)) {
            try {
                $client->createFolder($target);
                $this->line("  cartella {$target} creata");
            } catch (\Throwable $e) {
                // La cartella può esistere ma non essere sottoscritta: il server risponde ALREADYEXISTS.
                if (! str_contains($e->getMessage(), 'ALREADYEXISTS')) {
                    throw $e;
                }
            }
        }

        $moved = 0;

        foreach ($matched as [$message]) {
            $messageId = trim((string) $message->getMessageId(), " <>");
            $message->move($target);
            $moved++;

            if ($this->option('classification') && $messageId !== '') {
                IncomingEmail::where('company_id', $account->company_id)->where('message_id', $messageId)
                    ->update(['classification' => $this->option('classification')]);
            }
        }

        $this->info("  spostate {$moved}");
    }
}
