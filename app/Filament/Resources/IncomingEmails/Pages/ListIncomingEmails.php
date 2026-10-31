<?php

namespace App\Filament\Resources\IncomingEmails\Pages;

use App\Filament\Resources\IncomingEmails\IncomingEmailResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListIncomingEmails extends ListRecords
{
    protected static string $resource = IncomingEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scarica_email')
                ->label('Scarica email')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->modalDescription(fn () => auth()->user()?->isSuperAdmin()
                    ? 'Scarica le nuove email da tutte le caselle attive.'
                    : 'Scarica le nuove email dalle caselle attive della tua società.')
                ->action(function (): void {
                    set_time_limit(300);

                    $user = auth()->user();
                    // Chi non è super admin scarica solo le caselle della propria società.
                    $exit = Artisan::call('mail:fetch', $user->isSuperAdmin() ? [] : ['--company' => $user->company_id ?? 0]);
                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title($exit === 0 ? 'Scaricamento completato' : 'Scaricamento con errori')
                        ->body($output !== '' ? nl2br(e($output)) : null)
                        ->color($exit === 0 ? 'success' : 'danger')
                        ->persistent($exit !== 0)
                        ->send();
                }),
        ];
    }
}
