<?php

namespace App\Services\Bpm;

use App\Jobs\SendBpmEvent;
use App\Models\IncomingEmail;

/**
 * Eventi che unicopec comunica a UnicoBPM (`POST /api/events`): per ogni email o PEC ricevuta parte `email.ricevuta` e UnicoBPM,
 * in base al trigger «evento esterno» dei processi, decide quali avviare. Il `event_id` è fisso per email: un nuovo invio non avvia
 * due processi. Si comunicano i dati essenziali e un riferimento (`subject_ref` = id email): mai il corpo né gli allegati.
 * I dettagli si leggono con GET /api/incoming-emails/{id}.
 */
class BpmEvents
{
    public static function isConfigured(): bool
    {
        return filled(config('services.bpm.url')) && filled(config('services.bpm.key'));
    }

    /** @return array<string,mixed> */
    public static function received(IncomingEmail $email): array
    {
        return [
            'event_id' => "email.ricevuta:{$email->id}",
            'event' => 'email.ricevuta',
            'source' => 'unicopec',
            'subject_ref' => (string) $email->id,
            'occurred_at' => ($email->received_at ?? now())->toIso8601String(),
            'data' => [
                // UnicoBPM lega la società dalla partita IVA, non dall'id locale.
                'company_vat' => $email->company?->vat_number ?: $email->company?->piva,
                'mail_account_id' => $email->mail_account_id,
                'canale' => $email->mailAccount?->is_pec ? 'pec' : 'email',
                'mittente' => $email->from_email,
                'oggetto' => $email->subject,
                'classificazione' => $email->classification,
                'ricevuta_il' => $email->received_at?->toIso8601String(),
                'allegati' => $email->getMedia(IncomingEmail::ATTACHMENTS)->count(),
            ],
        ];
    }

    /** Mette in coda la comunicazione (con ritentativi) se UnicoBPM è configurato. Le ricevute di sistema non avviano processi. */
    public static function dispatchReceived(IncomingEmail $email): void
    {
        if (! self::isConfigured() || $email->classification === 'provider_notification') {
            return;
        }

        SendBpmEvent::dispatch(self::received($email));
    }
}
