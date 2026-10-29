<?php

namespace App\Filament\Resources\MailAccounts\Tables;

use App\Models\MailAccount;
use App\Services\Mail\MailAccountTester;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MailAccountsTable
{
    public static function configure(Table $table, bool $withCompany = true): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Etichetta')->sortable()->searchable(),
                TextColumn::make('company.name')->label('Società')->sortable()->searchable()->visible($withCompany),
                TextColumn::make('email_address')->label('Indirizzo')->searchable()->copyable(),
                IconColumn::make('is_pec')->label('PEC')->boolean(),
                TextColumn::make('auth_type')
                    ->label('Autenticazione')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'oauth2' ? 'OAuth 2.0' : 'Password')
                    ->color(fn (?string $state) => $state === 'oauth2' ? 'success' : 'gray'),
                TextColumn::make('imap_host')->label('Server IMAP')->toggleable(),
                TextColumn::make('last_synced_at')->label('Ultima lettura')->dateTime('d/m/Y H:i')->placeholder('mai')->sortable(),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')->label('Società')->relationship('company', 'name')->visible($withCompany),
            ])
            ->recordActions([
                self::connectMicrosoftAction(),
                self::adminConsentLinkAction(),
                Action::make('test')
                    ->label('Prova connessione')
                    ->icon('heroicon-o-signal')
                    ->action(function (MailAccount $record): void {
                        $result = app(MailAccountTester::class)->test($record);

                        Notification::make()
                            ->title($result['ok'] ? 'Connessione riuscita' : 'Connessione fallita')
                            ->body($result['message'])
                            ->color($result['ok'] ? 'success' : 'danger')
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Collega una casella Microsoft 365 con OAuth 2.0 (l'accesso IMAP con password è disattivato da Microsoft). */
    public static function connectMicrosoftAction(): Action
    {
        return Action::make('collega_microsoft')
            ->label(fn (MailAccount $record) => $record->auth_type === 'oauth2' && $record->provider === 'microsoft' ? 'Ricollega account Microsoft' : 'Collega account Microsoft')
            ->icon('heroicon-o-link')
            ->color('info')
            ->visible(fn (MailAccount $record) => $record->provider === 'microsoft' || str_contains((string) $record->imap_host, 'office365') || str_contains((string) $record->imap_host, 'outlook'))
            ->url(fn (MailAccount $record) => route('mail-oauth.microsoft.redirect', $record));
    }

    /** Mostra il link di consenso da inviare all'amministratore del tenant Microsoft 365 che ospita la casella. */
    public static function adminConsentLinkAction(): Action
    {
        return Action::make('link_consenso_admin')
            ->label('Link consenso amministratore')
            ->icon('heroicon-o-shield-check')
            ->color('gray')
            ->visible(fn (MailAccount $record) => $record->provider === 'microsoft' || str_contains((string) $record->imap_host, 'office365') || str_contains((string) $record->imap_host, 'outlook'))
            ->action(function (): void {
                $url = app(\App\Services\Mail\MicrosoftMailOAuth::class)->adminConsentUrl();

                Notification::make()->info()->persistent()->title('Link per l\'amministratore del tenant Microsoft 365')
                    ->body(new \Illuminate\Support\HtmlString('Invia questo indirizzo a un amministratore globale del tenant che ospita la casella: approvando, l\'app UnicoPEC può collegare le caselle.<br><br><a href="'.e($url).'" target="_blank" style="word-break:break-all;text-decoration:underline">'.e($url).'</a>'))
                    ->send();
            });
    }
}
