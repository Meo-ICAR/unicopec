<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Collegamento di una casella Microsoft 365 con OAuth 2.0 (flusso con codice di autorizzazione): l'utente accede con la casella,
 * Microsoft restituisce un access token e un refresh token che si salvano cifrati. Microsoft ha spento l'accesso IMAP con password.
 */
class MicrosoftMailOAuth
{
    /** IMAP in delega + refresh token. */
    public const SCOPES = 'offline_access openid email https://outlook.office.com/IMAP.AccessAsUser.All';

    public static function redirectUri(): string
    {
        return (string) (config('services.microsoft.mail_redirect') ?: url('/mail-oauth/microsoft/callback'));
    }

    public function authorizationUrl(MailAccount $account, string $state): string
    {
        return "https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/authorize?".http_build_query([
            'client_id' => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
            'login_hint' => $account->email_address,
            'prompt' => 'select_account',
        ]);
    }

    public static function newState(): string
    {
        return Str::random(40);
    }

    /** Scambia il codice con i token e li salva sulla casella; rifiuta se l'utente ha acceduto con un altro indirizzo. */
    public function complete(MailAccount $account, string $code): void
    {
        $response = Http::asForm()->timeout(20)->post("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token", [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
            'scope' => self::SCOPES,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Microsoft ha rifiutato il collegamento: '.mb_substr((string) $response->json('error_description'), 0, 200));
        }

        $signedIn = $this->signedInAddress((string) $response->json('id_token'));

        if ($signedIn !== null && strcasecmp($signedIn, $account->email_address) !== 0) {
            throw new RuntimeException("Hai acceduto come {$signedIn}, ma la casella è {$account->email_address}: riprova accedendo con l'account della casella.");
        }

        if (blank($response->json('refresh_token'))) {
            throw new RuntimeException('Microsoft non ha restituito il refresh token (manca il permesso offline_access): il collegamento non durerebbe.');
        }

        $account->forceFill([
            'auth_type' => 'oauth2',
            'provider' => 'microsoft',
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();
    }

    /** Indirizzo con cui l'utente ha acceduto, letto dall'id_token ricevuto direttamente dall'endpoint dei token. */
    private function signedInAddress(string $idToken): ?string
    {
        $payload = json_decode((string) base64_decode(strtr(explode('.', $idToken)[1] ?? '', '-_', '+/')), true);

        return $payload['email'] ?? $payload['preferred_username'] ?? null;
    }

    private function tenant(): string
    {
        return (string) (config('services.microsoft.mail_tenant') ?: config('services.microsoft.tenant') ?: 'common');
    }

    /** App registrata nel tenant che ospita le caselle (MICROSOFT_MAIL_CLIENT_ID); in mancanza, quella del login. */
    private function clientId(): ?string
    {
        return config('services.microsoft.mail_client_id') ?: config('services.microsoft.client_id');
    }

    private function clientSecret(): ?string
    {
        return config('services.microsoft.mail_client_secret') ?: config('services.microsoft.client_secret');
    }
}
