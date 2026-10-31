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

    /**
     * `email.richiesta`: l'AI ha riconosciuto una richiesta operativa nell'email (es. `rettifica_iban`). Come per gli altri eventi non porta il corpo:
     * l'IBAN è mascherato (paese e ultime 4 cifre), il valore completo si legge con GET /api/incoming-emails/{id}. `controlli` elenca i segnali di rischio
     * calcolati dal codice; UnicoBPM deve trattare una richiesta con controlli come «da verificare», mai come automatica.
     *
     * @param  array{intent: string, confidence: float, summary: string, entities: array<string, mixed>, flags: array<int, string>, confronto?: array{esito: string, proforma: ?float, dettaglio: string}}  $intent
     * @return array<string,mixed>
     */
    public static function request(IncomingEmail $email, array $intent): array
    {
        $entities = $intent['entities'];

        if (isset($entities['iban'])) {
            $entities['iban'] = substr($entities['iban'], 0, 2).str_repeat('*', max(0, strlen($entities['iban']) - 6)).substr($entities['iban'], -4);
        }

        return self::base($email, 'email.richiesta') + [
            'data' => self::data($email) + [
                'richiesta' => $intent['intent'],
                'sintesi' => $intent['summary'],
                'affidabilita' => $intent['confidence'],
                'dati' => $entities,
                'controlli' => $intent['flags'],
                // Per i modelli email di UnicoBPM, che leggono solo valori semplici.
                'controlli_testo' => implode(', ', $intent['flags']),
                'verifica_richiesta' => $intent['flags'] !== [],
            ] + (isset($intent['confronto']) ? [
                // Per le fatture: esito del confronto con il totale del proforma (letto dall'allegato con l'AI).
                'confronto' => ['esito' => $intent['confronto']['esito'], 'proforma' => $intent['confronto']['proforma'], 'dettaglio' => $intent['confronto']['dettaglio']],
            ] : []),
        ];
    }

    /**
     * `fornitore.iban_variato`: l'IBAN nelle fatture di un agente è diverso da quello in scheda o dalle fatture precedenti. Avvia in UnicoBPM la
     * verifica (richiamata, documento della banca). Gli IBAN sono mascherati; `event_id` è per agente e nuovo IBAN, quindi un rinvio non avvia due processi.
     *
     * @param  array<string, mixed>  $state da IbanAlignment::state()
     * @return array<string,mixed>
     */
    public static function ibanChanged(array $state, ?IncomingEmail $email = null): array
    {
        $mask = fn (?string $iban) => $iban === null ? null : substr($iban, 0, 2).str_repeat('*', max(0, strlen($iban) - 6)).substr($iban, -4);
        $company = \Unico\Core\Models\Company::query()->find($state['company_id']);

        return [
            'event_id' => 'fornitore.iban_variato:'.$state['fornitore_id'].':'.substr(sha1((string) $state['iban_fattura']), 0, 10),
            'event' => 'fornitore.iban_variato',
            'source' => 'unicopec',
            'subject_ref' => (string) $state['fornitore_id'],
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'company_vat' => $company?->vat_number ?: $company?->piva,
                'fornitore' => $state['nome'],
                'fornitore_id' => $state['fornitore_id'],
                'motivo' => $state['motivo'],
                'iban_nuovo' => $mask($state['iban_fattura']),
                'iban_scheda' => $mask($state['iban_scheda']),
                'iban_precedenti' => implode(', ', array_map($mask, $state['iban_precedenti'])),
                'email_id' => $email?->id ?? $state['email_id'],
            ],
        ];
    }

    public static function dispatchIbanChanged(array $state, ?IncomingEmail $email = null): void
    {
        SendBpmEvent::dispatch(self::ibanChanged($state, $email), $email?->id);
    }

    public static function dispatchReceived(IncomingEmail $email, ?Classification $classification = null): void
    {
        if (self::skips($email)) {
            return;
        }

        SendBpmEvent::dispatch(self::received($email, $classification), $email->id);
    }

    public static function dispatchAssigned(IncomingEmail $email, Employee $employee, string $role): void
    {
        if (self::skips($email)) {
            return;
        }

        SendBpmEvent::dispatch(self::assigned($email, $employee, $role), $email->id);
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
