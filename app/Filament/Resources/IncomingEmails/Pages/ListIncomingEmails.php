<?php

namespace App\Filament\Resources\IncomingEmails\Pages;

use App\Filament\Resources\IncomingEmails\IncomingEmailResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
                ->iconButton()
                ->tooltip('Scarica email')
                ->modalDescription(fn () => auth()->user()?->isSuperAdmin()
                    ? 'Scarica le nuove email da tutte le caselle attive.'
                    : 'Scarica le nuove email dalle caselle attive della tua società.')
                ->schema([
                    Select::make('days')->label('Periodo')->options([
                        '' => 'Dall\'ultima lettura',
                        '30' => 'Ultimi 30 giorni',
                        '90' => 'Ultimi 90 giorni',
                        '365' => 'Ultimo anno',
                    ])->default('')->helperText('Le email già scaricate non vengono duplicate. Per recuperare lo storico di una casella scegli un periodo più lungo.'),
                ])
                ->action(function (array $data): void {
                    set_time_limit(300);

                    $user = auth()->user();
                    // Chi non è super admin scarica solo le caselle della propria società.
                    $options = $user->isSuperAdmin() ? [] : ['--company' => $user->company_id ?? 0];

                    if (filled($data['days'] ?? null)) {
                        $options['--days'] = (int) $data['days'];
                        $options['--limit'] = 500;
                    }

                    $exit = Artisan::call('mail:fetch', $options);
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
