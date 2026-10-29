<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;
use App\Models\EmailEvent;
use App\Models\Employee;
use App\Models\IncomingEmail;
use Illuminate\Database\Eloquent\Builder;

/**
 * Anomalie di un'email, in parte calcolate al volo dallo stato attuale e in parte lette dal registro (errori non ancora risolti):
 * non classificata da oltre 1 ora, classificata ma senza referente, referente non più valido (inattivo o senza email),
 * errore registrato (evento BPM non consegnato, inoltro non riuscito, nessun referente trovato).
 */
class EmailAnomalies
{
    /** Categorie che non richiedono un referente. */
    private static function notRoutable(): array
    {
        return collect(EmailCategory::cases())->reject->isRoutable()->map->value->all();
    }

    /** Limita la query alle email con almeno un'anomalia. */
    public static function scope(Builder $query): Builder
    {
        $routable = collect(EmailCategory::cases())->filter->isRoutable()->map->value->all();

        return $query->where(function (Builder $q) use ($routable) {
            $q->where(fn ($q) => $q->whereNull('classification')->where('received_at', '<', now()->subHour()))
                ->orWhere(fn ($q) => $q->whereIn('classification', $routable)->whereNull('related_id')->where('received_at', '<', now()->subHour()))
                ->orWhere(fn ($q) => $q->whereIn('classification', $routable)->whereNotNull('related_id')->whereNotExists(
                    fn ($sub) => $sub->selectRaw('1')->from('employees')->whereColumn('employees.id', 'incoming_emails.related_id')->where('employees.is_active', true)->whereNotNull('employees.email')->where('employees.email', '!=', '')->whereNull('employees.deleted_at')
                ))
                ->orWhere(fn ($q) => $q->whereNotNull('awaiting_since')->whereNull('taken_at')->where('awaiting_since', '<', now()->subDay()))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('email_events')->whereColumn('email_events.incoming_email_id', 'incoming_emails.id')->where('email_events.kind', EmailEvent::ANOMALY)->where('email_events.status', 'error'));
        });
    }

    /** @return array<int, string> descrizioni leggibili */
    public static function for(IncomingEmail $email): array
    {
        $found = [];
        $category = EmailCategory::tryFrom((string) $email->classification);

        if ($email->classification === null && $email->received_at?->lt(now()->subHour())) {
            $found[] = 'Non ancora classificata (ricevuta da oltre 1 ora)';
        }

        if ($category?->isRoutable()) {
            if ($email->related_id === null && $email->received_at?->lt(now()->subHour())) {
                $found[] = 'Classificata ma senza referente';
            }

            if ($email->related_id !== null) {
                $employee = $email->related;

                if (! $employee instanceof Employee || ! $employee->is_active || blank($employee->email)) {
                    $found[] = 'Referente non valido (inattivo o senza email)';
                }
            }
        }

        if ($email->awaiting_since && ! $email->taken_at && $email->awaiting_since->lt(now()->subDay())) {
            $found[] = 'Inoltrata ma non ancora presa in carico (da oltre 24 ore)';
        }

        foreach ($email->events->where('kind', EmailEvent::ANOMALY)->where('status', 'error') as $event) {
            $found[] = $event->message;
        }

        return array_values(array_unique($found));
    }

    /** Esito della presa in carico: cosa è successo dopo lo smistamento. @return array{label: string, color: string} */
    public static function outcome(IncomingEmail $email): array
    {
        $events = $email->events;

        if ($email->classification === EmailCategory::ProviderNotification->value) {
            return ['label' => 'Ricevuta del gestore', 'color' => 'gray'];
        }

        if ($email->taken_at) {
            return ['label' => 'Presa in carico da '.($email->takenBy?->name ?? '—'), 'color' => 'success'];
        }

        if ($email->awaiting_since) {
            return ['label' => 'In attesa di presa in carico', 'color' => 'warning'];
        }

        if ($events->contains(fn ($e) => $e->kind === EmailEvent::BPM_EVENT && ($e->meta['started'] ?? 0) > 0)) {
            return ['label' => 'Processo BPM avviato', 'color' => 'success'];
        }

        $forwarded = $events->where('kind', EmailEvent::FORWARDED)->sortByDesc('id')->first();

        if ($forwarded) {
            return match ($forwarded->status) {
                'ok' => ['label' => 'Inoltrata al referente', 'color' => 'success'],
                'warning' => ['label' => 'Inoltro simulato', 'color' => 'warning'],
                default => ['label' => 'Inoltro non riuscito', 'color' => 'danger'],
            };
        }

        if ($email->related_id !== null) {
            return ['label' => 'Assegnata, in attesa di BPM', 'color' => 'info'];
        }

        return ['label' => '—', 'color' => 'gray'];
    }
}
