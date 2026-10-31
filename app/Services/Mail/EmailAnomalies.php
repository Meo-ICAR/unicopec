<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;
use App\Models\EmailEvent;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
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
                ->orWhere(fn ($q) => $q->whereIn('classification', $routable)->whereNotNull('related_id')->whereNotExists(
                    fn ($sub) => $sub->selectRaw('1')->from('protocols')->whereColumn('protocols.incoming_email_id', 'incoming_emails.id')->where('protocols.type', Protocol::SMISTAMENTO)
                )->whereExists(
                    fn ($sub) => $sub->selectRaw('1')->from('protocol_registers')->whereColumn('protocol_registers.company_id', 'incoming_emails.company_id')->where('protocol_registers.is_active', true)
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

                if (! $email->protocols()->where('type', Protocol::SMISTAMENTO)->exists() && ProtocolRegister::query()->where('company_id', $email->company_id)->where('is_active', true)->exists()) {
                    $found[] = 'Smistata ma senza protocollo';
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

        return match (true) {
            $email->classification === null => ['label' => 'Da classificare', 'color' => 'gray'],
            $email->classification === EmailCategory::NonRilevante->value => ['label' => 'Non rilevante', 'color' => 'gray'],
            default => ['label' => 'Da smistare', 'color' => 'warning'],
        };
    }

    /** @return array<string, string> codice => etichetta, nell'ordine in cui si valutano (il primo che vale decide) */
    public static function outcomeLabels(): array
    {
        return [
            'ricevuta_gestore' => 'Ricevuta del gestore',
            'presa_in_carico' => 'Presa in carico',
            'in_attesa' => 'In attesa di presa in carico',
            'bpm_avviato' => 'Processo BPM avviato',
            'inoltrata' => 'Inoltrata al referente',
            'inoltro_simulato' => 'Inoltro simulato',
            'inoltro_fallito' => 'Inoltro non riuscito',
            'assegnata' => 'Assegnata, in attesa di BPM',
            'da_classificare' => 'Da classificare',
            'non_rilevante' => 'Non rilevante',
            'da_smistare' => 'Da smistare',
        ];
    }

    /**
     * Lo stesso esito di `outcome()` come espressione SQL (codice di `outcomeLabels()`), per ordinare e filtrare la tabella senza caricare i record.
     * Va tenuta allineata a `outcome()`.
     */
    public static function outcomeSql(): string
    {
        $event = fn (string $where) => "exists (select 1 from email_events ev where ev.incoming_email_id = incoming_emails.id and ev.kind = '".'%s'."' and {$where})";
        $bpm = sprintf($event("coalesce(json_extract(ev.meta, '\$.started'), 0) > 0"), EmailEvent::BPM_EVENT);
        $lastForward = "(select ev.status from email_events ev where ev.incoming_email_id = incoming_emails.id and ev.kind = '".EmailEvent::FORWARDED."' order by ev.id desc limit 1)";

        return "case
            when incoming_emails.classification = '".EmailCategory::ProviderNotification->value."' then 'ricevuta_gestore'
            when incoming_emails.taken_at is not null then 'presa_in_carico'
            when incoming_emails.awaiting_since is not null then 'in_attesa'
            when {$bpm} then 'bpm_avviato'
            when {$lastForward} = 'ok' then 'inoltrata'
            when {$lastForward} = 'warning' then 'inoltro_simulato'
            when {$lastForward} is not null then 'inoltro_fallito'
            when incoming_emails.related_id is not null then 'assegnata'
            when incoming_emails.classification is null then 'da_classificare'
            when incoming_emails.classification = '".EmailCategory::NonRilevante->value."' then 'non_rilevante'
            else 'da_smistare' end";
    }
}
