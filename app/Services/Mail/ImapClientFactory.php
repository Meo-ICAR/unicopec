<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * Costruisce il client IMAP di una casella con password oppure con OAuth 2.0 (Microsoft), rinnovando l'access token
 * con il refresh token quando è scaduto o sta per scadere. Il client non è connesso: la connessione spetta a chi lo usa.
 */
class ImapClientFactory
{
    public function make(MailAccount $account): Client
    {
        $oauth = $account->auth_type === 'oauth2';

        if ($oauth) {
            $this->refreshIfNeeded($account);
        }

        return (new ClientManager)->make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => in_array($account->imap_encryption, [null, '', 'none'], true) ? false : $account->imap_encryption,
            'validate_cert' => true,
            'username' => $account->imap_username,
            'password' => $oauth ? $account->access_token : $account->imap_password,
            'protocol' => 'imap',
            'authentication' => $oauth ? 'oauth' : null,
        ]);
    }

    public function refreshIfNeeded(MailAccount $account): void
    {
        $expiring = ! $account->token_expires_at || $account->token_expires_at->copy()->subMinutes(5)->isPast();

        if (! $expiring) {
            return;
        }

        if (blank($account->refresh_token)) {
            throw new RuntimeException("Casella #{$account->id}: token scaduto e nessun refresh token, serve ricollegare l'account.");
        }

        if ($account->provider !== 'microsoft') {
            throw new RuntimeException("Casella #{$account->id}: il rinnovo del token è supportato solo per Microsoft.");
        }

        $config = config('services.microsoft');
        $response = Http::asForm()->timeout(20)->post("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token", [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'scope' => MicrosoftMailOAuth::SCOPES,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Casella #{$account->id}: rinnovo del token rifiutato da Microsoft (".mb_substr((string) $response->json('error_description'), 0, 120).'): serve ricollegare l\'account.');
        }

        $account->forceFill([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?: $account->refresh_token,
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();
    }

    private function tenant(): string
    {
        return (string) (config('services.microsoft.mail_tenant') ?: config('services.microsoft.tenant') ?: 'common');
    }
}
