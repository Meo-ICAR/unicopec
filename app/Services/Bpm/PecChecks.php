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
}
