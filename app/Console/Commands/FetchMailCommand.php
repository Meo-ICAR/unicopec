<?php

namespace App\Console\Commands;

use App\Models\IncomingEmail;
use App\Models\MailAccount;
use App\Services\Mail\PecEnvelope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\ClientManager;

class FetchMailCommand extends Command
{
    protected $signature = 'mail:fetch
        {account? : ID della casella (se omesso, tutte quelle attive)}
        {--limit=50 : Massimo di email nuove importate per casella}
        {--days= : Quanti giorni indietro leggere (default: dall\'ultima sincronizzazione, o 30)}
        {--dry-run : Legge e mostra senza salvare}';

    protected $description = 'Scarica email e PEC in incoming_emails, aprendo la busta PEC (postacert.eml)';

    public function handle(PecEnvelope $envelope): int
    {
        $accounts = MailAccount::query()
            ->where('is_active', true)
            ->when($this->argument('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($accounts->isEmpty()) {
            $this->error('Nessuna casella attiva trovata.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($accounts as $account) {
            try {
                $this->fetch($account, $envelope);
            } catch (\Throwable $e) {
                $failed = true;
                $this->error("#{$account->id} {$account->email_address}: ".get_class($e).': '.$e->getMessage());
                Log::error('mail:fetch fallito', ['mail_account_id' => $account->id, 'error' => $e->getMessage()]);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function fetch(MailAccount $account, PecEnvelope $envelope): void
    {
        $client = (new ClientManager)->make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => in_array($account->imap_encryption, [null, '', 'none'], true) ? false : $account->imap_encryption,
            'validate_cert' => true,
            'username' => $account->imap_username,
            'password' => $account->imap_password,
            'protocol' => 'imap',
        ]);
        $client->connect();

        $days = $this->option('days');
        $since = $days !== null
            ? now()->subDays((int) $days)
            : ($account->last_synced_at?->copy()->subDays(2) ?? now()->subDays(30));

        // Si scansiona una finestra di date e si deduplica per message_id: il flag «letta» non è affidabile.
        // leaveUnread: nessuna modifica ai flag sul server.
        $messages = $client->getFolder('INBOX')->messages()->since($since)->leaveUnread()->get();

        $imported = $duplicates = 0;
        $limit = (int) $this->option('limit');

        foreach ($messages as $message) {
            if ($imported >= $limit) {
                break;
            }

            $messageId = $this->normalizeId((string) $message->getMessageId())
                ?: 'fallback-'.hash('sha256', $account->id.'|'.$message->getSubject().'|'.$message->getDate());

            if (IncomingEmail::withTrashed()->where('company_id', $account->company_id)->where('message_id', $messageId)->exists()) {
                $duplicates++;

                continue;
            }

            $envelopeData = $envelope->open($message);
            $busta = $message->getFrom()[0] ?? null;
            $isPec = $envelopeData['pec_type'] !== null;

            $this->line(sprintf(
                '  %s | %-16s | %s | %s',
                $message->getDate(),
                $envelopeData['pec_type'] ?? 'email',
                $envelopeData['from_email'] ?? '?',
                mb_substr((string) $envelopeData['subject'], 0, 70),
            ));

            if ($this->option('dry-run')) {
                $imported++;

                continue;
            }

            $references = implode(' ', array_map(fn ($r) => $this->normalizeId((string) $r), (array) $message->getReferences()->toArray()));
            $inReplyTo = $this->normalizeId((string) $message->getInReplyTo());

            $email = IncomingEmail::create([
                'company_id' => $account->company_id,
                'mail_account_id' => $account->id,
                'message_id' => $messageId,
                'in_reply_to' => $inReplyTo ?: null,
                'references' => $references ?: null,
                'thread_id' => $references !== '' ? strtok($references, ' ') : ($inReplyTo ?: $messageId),
                // In una PEC il mittente della busta è il gestore: si salvano mittente e oggetto veri.
                'from_email' => $envelopeData['from_email'] ?? $busta->mail ?? 'sconosciuto',
                'from_name' => mb_substr((string) ($envelopeData['from_name'] ?: ($envelopeData['from_email'] ?? $busta->personal ?? '')), 0, 255),
                'to' => $this->addresses($message->getTo()),
                'cc' => $this->addresses($message->getCc()),
                'subject' => mb_substr($envelopeData['subject'] ?: '(Senza oggetto)', 0, 255),
                'body_text' => $envelopeData['text'],
                'body_html' => $envelopeData['html'],
                'received_at' => $this->date($message),
                'is_read' => false,
                // Le ricevute di sistema (accettazione, consegna...) non hanno un referente.
                'classification' => $isPec && $envelopeData['pec_type'] !== 'messaggio' ? 'provider_notification' : null,
            ]);

            $this->storeAttachments($envelopeData['attachments'], $email);
            $imported++;

            // L'evento per UnicoBPM parte dopo la classificazione (mail:classify), così porta già categoria e sintesi.
        }

        if (! $this->option('dry-run')) {
            $account->forceFill(['last_synced_at' => now()])->save();
        }

        $this->info("#{$account->id} {$account->email_address}: nuove {$imported}, già presenti {$duplicates}".($this->option('dry-run') ? ' (dry-run)' : ''));
    }

    /** @param array<int, object> $attachments */
    private function storeAttachments(array $attachments, IncomingEmail $email): void
    {
        foreach ($attachments as $attachment) {
            $name = $this->safeName((string) $attachment->getName());
            $tmp = tempnam(sys_get_temp_dir(), 'mail_att_');

            try {
                file_put_contents($tmp, $attachment->getContent());
                $email->addMedia($tmp)->usingFileName($name)->toMediaCollection(IncomingEmail::ATTACHMENTS);
            } catch (\Throwable $e) {
                Log::warning('Allegato email non archiviato', ['incoming_email_id' => $email->id, 'file' => $name, 'error' => $e->getMessage()]);
            } finally {
                @unlink($tmp);
            }
        }
    }

    private function safeName(string $name): string
    {
        $decoded = trim(str_replace(['/', '\\', "\0"], '-', @iconv_mime_decode($name, 0, 'UTF-8') ?: $name));

        return $decoded === '' ? 'allegato' : mb_substr($decoded, 0, 150);
    }

    /** @return array<int, array{email: ?string, name: ?string}> */
    private function addresses(mixed $addresses): array
    {
        $list = $addresses instanceof Attribute ? $addresses->all() : (is_array($addresses) ? $addresses : []);

        return array_values(array_map(fn ($a) => ['email' => $a->mail ?? null, 'name' => ($a->personal ?? '') ?: null], $list));
    }

    private function date(object $message): \Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse((string) $message->getDate());
        } catch (\Throwable) {
            return now();
        }
    }

    private function normalizeId(string $value): string
    {
        return mb_substr(trim($value, " \t\n\r\0\x0B<>"), 0, 255);
    }
}
