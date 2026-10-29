<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Webklex\PHPIMAP\ClientManager;

/** Prova la connessione IMAP di una casella senza modificare nulla sul server. */
class MailAccountTester
{
    /** @return array{ok: bool, message: string} */
    public function test(MailAccount $account): array
    {
        if ($account->auth_type === 'oauth2') {
            return ['ok' => false, 'message' => 'Il test della connessione OAuth 2.0 non è ancora disponibile.'];
        }

        try {
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
            $count = $client->getFolder('INBOX')->messages()->all()->count();

            return ['ok' => true, 'message' => "Connessione riuscita: {$count} messaggi in INBOX."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => class_basename($e).': '.$e->getMessage()];
        }
    }
}
