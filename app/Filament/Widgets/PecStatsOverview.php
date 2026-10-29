<?php

namespace App\Filament\Widgets;

use App\Enums\EmailCategory;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Models\MailAccount;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PecStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Panoramica PEC';

    protected function getStats(): array
    {
        $realMail = fn () => IncomingEmail::query()->where(fn ($q) => $q->whereNull('classification')->orWhere('classification', '!=', EmailCategory::ProviderNotification->value));
        $routable = collect(EmailCategory::cases())->filter->isRoutable()->map->value->all();

        $toRoute = IncomingEmail::query()->where(fn ($q) => $q->whereNull('classification')->orWhere(fn ($q) => $q->whereIn('classification', $routable)->whereNull('related_id')))->count();
        $anomalies = IncomingEmail::query()->withAnomalies()->count();
        $forwarded = EmailEvent::query()->where('kind', EmailEvent::FORWARDED)->where('status', 'ok')->where('created_at', '>=', now()->subDays(7))->count();
        $simulated = EmailEvent::query()->where('kind', EmailEvent::FORWARDED)->where('status', 'warning')->where('created_at', '>=', now()->subDays(7))->count();
        $processes = EmailEvent::query()->where('kind', EmailEvent::BPM_EVENT)->where('status', 'ok')->where('created_at', '>=', now()->subDays(7))->count();
        $staleAccounts = MailAccount::query()->where('is_active', true)->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subDay()))->count();

        return [
            Stat::make('Ricevute oggi', $realMail()->whereDate('received_at', today())->count())->description('Ultimi 7 giorni: '.$realMail()->where('received_at', '>=', now()->subDays(7))->count())->icon('heroicon-o-inbox-arrow-down'),
            Stat::make('Da smistare', $toRoute)->description('Da classificare o senza referente')->color($toRoute > 0 ? 'warning' : 'success')->icon('heroicon-o-arrows-right-left'),
            Stat::make('Con anomalie', $anomalies)->description('Da controllare')->color($anomalies > 0 ? 'danger' : 'success')->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Processi BPM avviati', $processes)->description('Ultimi 7 giorni')->icon('heroicon-o-cog-6-tooth'),
            Stat::make('Inoltrate al referente', $forwarded)->description($simulated > 0 ? "più {$simulated} simulate" : 'Ultimi 7 giorni')->icon('heroicon-o-paper-airplane'),
            Stat::make('Caselle ferme', $staleAccounts)->description('Non lette da oltre 24 ore')->color($staleAccounts > 0 ? 'danger' : 'success')->icon('heroicon-o-at-symbol'),
        ];
    }
}
