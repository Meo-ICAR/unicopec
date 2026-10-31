<?php

namespace App\Services\Bpm;

use App\Models\IncomingEmail;
use App\Models\MailAccount;

/**
 * Controlli che UnicoBPM può interrogare (GET /api/checks/{controllo}): solo valore, severity (ok|regular|warning|alert) e dettaglio.
 * Cosa farne e chi avvisare lo decide UnicoBPM con la RACI.
 */
class PecChecks
{
    /** @return array<string, string> codice => descrizione */
    public static function all(): array
    {
        return [
            'pec:non-smistate' => 'PEC ricevute da oltre 24 ore e non ancora assegnate a un referente.',
            'pec:non-lette' => 'PEC ricevute da oltre 3 giorni e ancora da leggere.',
            'fornitori:iban-non-allineati' => 'Agenti il cui IBAN in fattura non coincide con la scheda o non è stato verificato: finché non si allinea, il contabile viene avvisato ogni giorno.',
            'caselle:non-sincronizzate' => 'Caselle attive mai lette o ferme da oltre 24 ore (password scaduta o server irraggiungibile).',
        ];
    }

    /** @return array{value: int, severity: string, details: ?string}|null null se il controllo non esiste */
    public static function run(string $check): ?array
    {
        return match ($check) {
            'pec:non-smistate' => self::fromCount(
                self::messages()->whereNull('related_id')->where('received_at', '<', now()->subDay()),
                warningFrom: 1, alertFrom: 5, label: 'PEC senza referente da oltre 24 ore'
            ),
            'pec:non-lette' => self::fromCount(
                self::messages()->where('is_read', false)->where('received_at', '<', now()->subDays(3)),
                warningFrom: 1, alertFrom: 10, label: 'PEC non lette da oltre 3 giorni'
            ),
            'fornitori:iban-non-allineati' => self::ibanNotAligned(),
            'caselle:non-sincronizzate' => self::staleAccounts(),
            default => null,
        };
    }

    /** Le ricevute di sistema non hanno un referente e non contano. */
    private static function messages()
    {
        return IncomingEmail::query()->where(fn ($q) => $q->whereNull('classification')->orWhere('classification', '!=', 'provider_notification'));
    }

    private static function fromCount($query, int $warningFrom, int $alertFrom, string $label): array
    {
        $count = (clone $query)->count();
        $details = $count === 0 ? null : "{$count} {$label}: ".(clone $query)->oldest('received_at')->limit(3)->pluck('subject')->map(fn ($s) => '«'.mb_substr((string) $s, 0, 60).'»')->implode(', ').($count > 3 ? ' …' : '');

        return [
            'value' => $count,
            'severity' => $count >= $alertFrom ? 'alert' : ($count >= $warningFrom ? 'warning' : 'ok'),
            'details' => $details,
        ];
    }

    private static function staleAccounts(): array
    {
        $stale = MailAccount::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subDay()))
            ->pluck('email_address');

        return [
            'value' => $stale->count(),
            'severity' => $stale->isEmpty() ? 'ok' : 'alert',
            'details' => $stale->isEmpty() ? null : 'Ferme: '.$stale->implode(', '),
        ];
    }

    /** Gradi: regular da subito, warning dopo 3 giorni, alert dopo 7 (o subito se l'IBAN è cambiato e non è italiano). @return array{value: int, severity: string, details: ?string} */
    private static function ibanNotAligned(): array
    {
        $rows = app(\App\Services\Mail\IbanAlignment::class)->pending();

        if ($rows->isEmpty()) {
            return ['value' => 0, 'severity' => 'ok', 'details' => null];
        }

        $worst = $rows->max('giorni');
        $foreign = $rows->contains(fn ($r) => $r['motivo'] === \App\Services\Mail\IbanAlignment::CHANGED && ! str_starts_with((string) $r['iban_fattura'], 'IT'));
        $labels = [\App\Services\Mail\IbanAlignment::MISSING => 'IBAN non in scheda', \App\Services\Mail\IbanAlignment::CHANGED => 'IBAN variato', \App\Services\Mail\IbanAlignment::UNVERIFIED => 'IBAN da verificare'];

        return [
            'value' => $rows->count(),
            'severity' => $foreign || $worst >= 7 ? 'alert' : ($worst >= 3 ? 'warning' : 'regular'),
            // Solo nomi e motivo: gli IBAN interi non viaggiano nelle email.
            'details' => $rows->map(fn ($r) => "{$r['nome']} ({$labels[$r['motivo']]}, da {$r['giorni']} giorni, ...".substr((string) $r['iban_fattura'], -4).')')->implode('; '),
        ];
    }
}
