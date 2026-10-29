<?php

namespace App\Services\Mail;

use App\Models\MailAccount;

/** Prova la connessione IMAP di una casella senza modificare nulla sul server. */
class MailAccountTester
{
    /** @return array{ok: bool, message: string} */
    public function test(MailAccount $account): array
    {
        try {
            $client = app(ImapClientFactory::class)->make($account);
            $client->connect();
            $count = $client->getFolder('INBOX')->messages()->all()->count();

            return ['ok' => true, 'message' => "Connessione riuscita: {$count} messaggi in INBOX."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => class_basename($e).': '.$e->getMessage()];
        }
    }
}
