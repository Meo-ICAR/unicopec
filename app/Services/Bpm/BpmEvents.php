<?php

namespace App\Services\Bpm;

use App\Enums\EmailCategory;
use App\Jobs\SendBpmEvent;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Services\Mail\Classification;

/**
 * Eventi che unicopec comunica a UnicoBPM (`POST /api/events`), in coda e con ritentativi:
 * - `email.ricevuta`: l'email è stata importata e classificata (categoria, urgenza e sintesi nei dati);
 * - `email.assegnata`: l'email ha un referente (nome, email e ruolo).
 * Il `event_id` è fisso per email ed evento: un nuovo invio non avvia due processi. `subject_ref` è l'id dell'email; mai corpo né allegati
 * (i dettagli si leggono con GET /api/incoming-emails/{id}). Le ricevute del gestore PEC e i messaggi non rilevanti non generano eventi.
 */
class BpmEvents
{
    public static function isConfigured(): bool
    {
        return filled(config('services.bpm.url')) && filled(config('services.bpm.key'));
    }

    /** @return array<string,mixed> */
    public static function received(IncomingEmail $email, ?Classification $classification = null): array
    {
        return self::base($email, 'email.ricevuta') + [
            'data' => self::data($email) + array_filter([
                'urgenza' => $classification?->urgency,
                'sintesi' => $classification?->summary,
            ], fn ($v) => $v !== null),
        ];
    }

    /** @return array<string,mixed> */
    public static function assigned(IncomingEmail $email, Employee $employee, string $role): array
    {
        return self::base($email, 'email.assegnata') + [
            'data' => self::data($email) + [
                'referente' => ['id' => $employee->id, 'nome' => $employee->name, 'email' => $employee->email, 'ruolo' => $role],
            ],
        ];
    }

    public static function dispatchReceived(IncomingEmail $email, ?Classification $classification = null): void
    {
        if (self::skips($email)) {
            return;
        }

        SendBpmEvent::dispatch(self::received($email, $classification));
    }

    public static function dispatchAssigned(IncomingEmail $email, Employee $employee, string $role): void
    {
        if (self::skips($email)) {
            return;
        }

        SendBpmEvent::dispatch(self::assigned($email, $employee, $role));
    }

    private static function skips(IncomingEmail $email): bool
    {
        return ! self::isConfigured()
            || in_array($email->classification, [EmailCategory::ProviderNotification->value, EmailCategory::NonRilevante->value], true);
    }

    /** @return array<string,mixed> */
    private static function base(IncomingEmail $email, string $event): array
    {
        return [
            'event_id' => "{$event}:{$email->id}",
            'event' => $event,
            'source' => 'unicopec',
            'subject_ref' => (string) $email->id,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private static function data(IncomingEmail $email): array
    {
        return [
            // UnicoBPM lega la società dalla partita IVA, non dall'id locale.
            'company_vat' => $email->company?->vat_number ?: $email->company?->piva,
            'mail_account_id' => $email->mail_account_id,
            'canale' => $email->mailAccount?->is_pec ? 'pec' : 'email',
            'mittente' => $email->from_email,
            'oggetto' => $email->subject,
            'classificazione' => $email->classification,
            'ricevuta_il' => $email->received_at?->toIso8601String(),
            'allegati' => $email->getMedia(IncomingEmail::ATTACHMENTS)->count(),
        ];
    }
}
