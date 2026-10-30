<?php

namespace App\Filament\Resources\IncomingEmails\RelationManagers;

use App\Models\EmailEvent;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Cronologia di un'email: classificazione, assegnazione, eventi verso UnicoBPM, inoltri e anomalie. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Cronologia e anomalie';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s'),
                TextColumn::make('kind')->label('Passo')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        EmailEvent::CLASSIFIED => 'Classificazione',
                        EmailEvent::ASSIGNED => 'Assegnazione',
                        EmailEvent::BPM_EVENT => 'UnicoBPM',
                        EmailEvent::FORWARDED => 'Inoltro',
                        EmailEvent::ANOMALY => 'Anomalia',
                        EmailEvent::TAKEN => 'Presa in carico',
                        EmailEvent::PROTOCOLLED => 'Protocollo',
                        default => $state,
                    }),
                TextColumn::make('status')->label('Esito')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) { 'ok' => 'OK', 'warning' => 'Attenzione', 'error' => 'Errore', 'resolved' => 'Risolta', default => $state })
                    ->color(fn (string $state) => match ($state) { 'ok', 'resolved' => 'success', 'warning' => 'warning', 'error' => 'danger', default => 'gray' }),
                TextColumn::make('message')->label('Dettaglio')->wrap(),
            ])
            ->recordActions([
                Action::make('risolvi')
                    ->label('Segna risolta')
                    ->icon('heroicon-o-check')
                    ->visible(fn (EmailEvent $record) => $record->kind === EmailEvent::ANOMALY && $record->status === 'error')
                    ->action(fn (EmailEvent $record) => $record->update(['status' => 'resolved'])),
            ]);
    }
}
