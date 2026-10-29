<?php

namespace App\Services\Mail;

use App\Mail\ForwardedPec;
use App\Models\Employee;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use Illuminate\Support\Facades\Mail;

/**
 * Inoltra una PEC/email all'indirizzo del suo referente (usato quando in UnicoBPM non c'è un processo che la prenda in carico).
 * Con l'organigramma di ripiego (simulazione: i referenti sono persone di un'altra società) NON si scrive ai loro indirizzi reali:
 * l'inoltro va a `pec.forward.simulation_to` se impostato, altrimenti si registra solo come simulato.
 */
class EmailForwarder
{
    public function __construct(private EmailRouter $router) {}

    /** @return array{status: string, message: string} esito: ok | warning (simulato o già inoltrata) | error */
    public function forward(IncomingEmail $email, string $reason = 'Inoltro richiesto', bool $force = false): array
    {
        $employee = $email->related instanceof Employee ? $email->related : null;

        if (! $employee) {
            return $this->done($email, 'error', 'Inoltro impossibile: l\'email non ha un referente.', true);
        }

        if (blank($employee->email)) {
            return $this->done($email, 'error', "Inoltro impossibile: {$employee->name} non ha un indirizzo email.", true);
        }

        if (! $force && $email->events()->where('kind', EmailEvent::FORWARDED)->where('status', 'ok')->exists()) {
            return ['status' => 'warning', 'message' => 'Già inoltrata.'];
        }

        $to = $employee->email;
        $banner = null;
        $simulation = $this->router->orgCompanyId($email->company_id) !== (int) $email->company_id;

        if ($simulation) {
            $to = (string) config('pec.forward.simulation_to');
            $banner = "SIMULAZIONE: l'organigramma è quello di un'altra società; destinatario previsto {$employee->name} <{$employee->email}>.";

            if ($to === '') {
                return $this->done($email, 'warning', "Inoltro simulato: sarebbe andata a {$employee->name} <{$employee->email}> ({$reason}).", false);
            }
        }

        try {
            Mail::to($to)->send(new ForwardedPec($email, $employee, $banner, $this->roleOf($email)));
        } catch (\Throwable $e) {
            return $this->done($email, 'error', 'Inoltro non riuscito: '.mb_substr($e->getMessage(), 0, 200), true);
        }

        return $this->done($email, 'ok', "Inoltrata a {$to}".($simulation ? " (simulazione, previsto {$employee->name})" : " ({$employee->name})")." — {$reason}.", false);
    }

    private function roleOf(IncomingEmail $email): ?string
    {
        return $email->events()->where('kind', EmailEvent::ASSIGNED)->latest('id')->value('meta->role');
    }

    /** @return array{status: string, message: string} */
    private function done(IncomingEmail $email, string $status, string $message, bool $anomaly): array
    {
        EmailEvent::record($email, EmailEvent::FORWARDED, $status, $message);

        if ($anomaly) {
            EmailEvent::record($email, EmailEvent::ANOMALY, 'error', $message);
        }

        return ['status' => $status, 'message' => $message];
    }
}
