<?php

namespace App\Services\Mail;

use App\Mail\ForwardedPec;
use App\Models\EmailEvent;
use App\Models\Employee;
use App\Jobs\ArchiveEmail;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Services\Protocol\PresaInCaricoProtocoller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Inoltro manuale a un referente scelto da una persona (un dipendente o tutti quelli di un ruolo) e presa in carico.
 * Ogni destinatario riceve la PEC con un link firmato «Prendo in carico»: il primo che lo conferma diventa il referente.
 * Con l'organigramma di ripiego (simulazione) non si scrive ai destinatari veri, come in EmailForwarder.
 */
class EmailHandover
{
    public function __construct(private EmailRouter $router, private PresaInCaricoProtocoller $protocols) {}

    /** @return Collection<int, Employee> chi ha il ruolo, tra gli attivi con email dell'organigramma della società */
    public function holdersOf(IncomingEmail $email, string $role): Collection
    {
        return $this->staff($email)->filter(fn (Employee $e) => in_array($role, collect($e->employee_roles)->map(fn ($r) => mb_strtolower(trim((string) $r)))->all(), true))->values();
    }

    /** @return Collection<int, Employee> */
    public function staff(IncomingEmail $email): Collection
    {
        return Employee::query()->where('company_id', $this->router->orgCompanyId($email->company_id))->where('is_active', true)->whereNotNull('email')->where('email', '!=', '')->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, Employee>  $recipients
     * @return array{status: string, message: string}
     */
    public function send(IncomingEmail $email, Collection $recipients, ?string $note, string $target): array
    {
        $recipients = $recipients->filter(fn (Employee $e) => filled($e->email))->values();

        if ($recipients->isEmpty()) {
            return $this->record($email, 'error', 'Inoltro impossibile: nessun destinatario con un indirizzo email.', $target, [], true);
        }

        $simulation = $this->router->orgCompanyId($email->company_id) !== (int) $email->company_id;
        $simulationTo = (string) config('pec.forward.simulation_to');
        $sent = [];
        $failed = [];

        foreach ($recipients as $employee) {
            if ($simulation && $simulationTo === '') {
                $sent[] = $employee->name;

                continue;
            }

            $banner = $simulation ? "SIMULAZIONE: destinatario previsto {$employee->name} <{$employee->email}>." : null;

            try {
                Mail::to($simulation ? $simulationTo : $employee->email)->send(new ForwardedPec($email, $employee, $banner, null, $this->takeUrl($email, $employee), $note));
                $sent[] = $employee->name;
            } catch (\Throwable $e) {
                $failed[] = $employee->name;
            }
        }

        if (! $sent) {
            return $this->record($email, 'error', 'Inoltro non riuscito a nessun destinatario.', $target, $failed, true);
        }

        // Un inoltro singolo assegna subito il referente; in un gruppo lo diventa chi prende in carico.
        $email->forceFill(array_filter([
            'awaiting_since' => $email->taken_at ? null : now(),
            'related_type' => $recipients->count() === 1 ? 'employee' : null,
            'related_id' => $recipients->count() === 1 ? $recipients->first()->id : null,
        ], fn ($v, $k) => $k === 'awaiting_since' || $v !== null, ARRAY_FILTER_USE_BOTH))->save();

        $status = $simulation ? 'warning' : ($failed ? 'warning' : 'ok');
        $message = ($simulation ? 'Inoltro simulato' : 'Inoltrata').' a '.$target.' ('.implode(', ', $sent).')'.($failed ? '; non riuscito per '.implode(', ', $failed) : '').'. In attesa di presa in carico.';

        return $this->record($email, $status, $message, $target, $failed, false, $sent);
    }

    /**
     * Il primo che conferma vince; gli altri vedono chi ha già preso in carico. Se la società usa i registri di protocollo la presa in carico
     * crea il secondo protocollo, nel registro scelto: dal link tra quelli delle funzioni della persona, dal pannello (`manuale`) tra tutti quelli della società.
     * Senza un registro valido non cambia nulla e `error` dice perché.
     *
     * @return array{taken: bool, by: ?Employee, protocol: ?Protocol, error: ?string}
     */
    public function take(IncomingEmail $email, Employee $employee, string $via = 'link', ?int $registerId = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($email, $employee, $via, $registerId, $userId) {
            $locked = IncomingEmail::query()->lockForUpdate()->findOrFail($email->id);

            if ($locked->taken_at) {
                return ['taken' => $locked->taken_by_id === $employee->id, 'by' => $locked->takenBy, 'protocol' => null, 'error' => null];
            }

            $register = null;

            if ($this->protocols->isInUse($locked)) {
                $allowed = $via === 'link' ? $this->protocols->registersFor($locked, $employee) : $this->protocols->allFor($locked);

                if ($allowed->isEmpty()) {
                    return $this->refused('Nessun registro di protocollo è disponibile per la tua funzione: rivolgiti all\'amministratore.');
                }

                $register = $registerId ? $allowed->firstWhere('id', $registerId) : ($allowed->count() === 1 ? $allowed->first() : null);

                if (! $register) {
                    return $this->refused($registerId ? 'Il registro scelto non è consentito.' : 'Scegli il registro in cui protocollare.');
                }
            }

            $locked->forceFill([
                'taken_by_id' => $employee->id, 'taken_at' => now(), 'taken_via' => $via, 'awaiting_since' => null,
                'related_type' => 'employee', 'related_id' => $employee->id, 'is_read' => true,
            ])->save();

            EmailEvent::record($locked, EmailEvent::TAKEN, 'ok', "Presa in carico da {$employee->name}".($via === 'link' ? '' : ' (registrata a mano)'), ['employee_id' => $employee->id, 'via' => $via]);

            $protocol = null;

            if ($register) {
                $protocol = $this->protocols->protocol($locked, $employee, $register, $userId);
                EmailEvent::record($locked, EmailEvent::PROTOCOLLED, 'ok', "Protocollata {$protocol->identifier} (presa in carico)", ['protocol_id' => $protocol->id, 'identifier' => $protocol->identifier]);

                if (! $locked->archive_status) {
                    $locked->forceFill(['archive_status' => 'da_archiviare'])->save();
                }

                ArchiveEmail::dispatch($locked->id)->afterCommit();
            }

            return ['taken' => true, 'by' => $employee, 'protocol' => $protocol, 'error' => null];
        });
    }

    /** @return array{taken: bool, by: ?Employee, protocol: ?Protocol, error: ?string} */
    private function refused(string $error): array
    {
        return ['taken' => false, 'by' => null, 'protocol' => null, 'error' => $error];
    }

    private function takeUrl(IncomingEmail $email, Employee $employee): string
    {
        return URL::temporarySignedRoute('pec.take', now()->addDays(30), ['email' => $email->id, 'employee' => $employee->id]);
    }

    /** @param array<int, string> $names */
    private function record(IncomingEmail $email, string $status, string $message, string $target, array $failed, bool $anomaly, array $sent = []): array
    {
        EmailEvent::record($email, EmailEvent::FORWARDED, $status, $message, ['manual' => true, 'target' => $target, 'sent' => $sent, 'failed' => $failed]);

        if ($anomaly) {
            EmailEvent::record($email, EmailEvent::ANOMALY, 'error', $message);
        }

        return ['status' => $status, 'message' => $message];
    }
}
