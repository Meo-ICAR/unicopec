<?php

namespace App\Filament\Widgets;

use App\Enums\EmailCategory;
use App\Filament\Resources\IncomingEmails\IncomingEmailResource;
use App\Filament\Resources\MailAccounts\MailAccountResource;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Models\MailAccount;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PecStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Panoramica PEC';

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        $emails = fn () => IncomingEmail::query()->when($tenantId, fn ($q) => $q->where('company_id', $tenantId));
        $events = fn () => EmailEvent::query()->when($tenantId, fn ($q) => $q->whereIn('incoming_email_id', IncomingEmail::query()->where('company_id', $tenantId)->select('id')));

        $realMail = fn () => $emails()->where(fn ($q) => $q->whereNull('classification')->orWhere('classification', '!=', EmailCategory::ProviderNotification->value));
        $routable = collect(EmailCategory::cases())->filter->isRoutable()->map->value->all();

        $toRoute = $emails()->where(fn ($q) => $q->whereNull('classification')->orWhere(fn ($q) => $q->whereIn('classification', $routable)->whereNull('related_id')))->count();
        $anomalies = $emails()->withAnomalies()->count();
        $forwarded = $events()->where('kind', EmailEvent::FORWARDED)->where('status', 'ok')->where('created_at', '>=', now()->subDays(7))->count();
        $simulated = $events()->where('kind', EmailEvent::FORWARDED)->where('status', 'warning')->where('created_at', '>=', now()->subDays(7))->count();
        $processes = $events()->where('kind', EmailEvent::BPM_EVENT)->where('status', 'ok')->where('created_at', '>=', now()->subDays(7))->count();
        $staleAccounts = MailAccount::query()->when($tenantId, fn ($q) => $q->where('company_id', $tenantId))->where('is_active', true)->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subDay()))->count();

        return [
            Stat::make('Ricevute oggi', $realMail()->whereDate('received_at', today())->count())->description('Ultimi 7 giorni: '.$realMail()->where('received_at', '>=', now()->subDays(7))->count())->icon('heroicon-o-inbox-arrow-down')->url(self::emails('oggi')),
            Stat::make('Da smistare', $toRoute)->description('Da classificare o senza referente')->color($toRoute > 0 ? 'warning' : 'success')->icon('heroicon-o-arrows-right-left')->url(self::emails('da_smistare')),
            Stat::make('Con anomalie', $anomalies)->description('Da controllare')->color($anomalies > 0 ? 'danger' : 'success')->icon('heroicon-o-exclamation-triangle')->url(self::emails('con_anomalie')),
            Stat::make('Processi BPM avviati', $processes)->description('Ultimi 7 giorni')->icon('heroicon-o-cog-6-tooth')->url(self::emails('bpm_7g')),
            Stat::make('Inoltrate al referente', $forwarded)->description($simulated > 0 ? "più {$simulated} simulate" : 'Ultimi 7 giorni')->icon('heroicon-o-paper-airplane')->url(self::emails('inoltrate_7g')),
            Stat::make('Caselle ferme', $staleAccounts)->description('Non lette da oltre 24 ore')->color($staleAccounts > 0 ? 'danger' : 'success')->icon('heroicon-o-at-symbol')->url(MailAccountResource::getUrl('index', ['filters' => ['ferme' => ['isActive' => true]]])),
        ];
    }

    /** Elenco delle email con il filtro già attivo: il numero del riquadro e quello in elenco coincidono. */
    private static function emails(string $filter): string
    {
        return IncomingEmailResource::getUrl('index', ['filters' => [$filter => ['isActive' => true]]]);
    }
}
