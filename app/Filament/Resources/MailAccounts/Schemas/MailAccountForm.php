<?php

namespace App\Filament\Resources\MailAccounts\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MailAccountForm
{
    /** @param  bool  $withCompany  false quando il form sta dentro la società (relation manager) */
    public static function configure(Schema $schema, bool $withCompany = true): Schema
    {
        return $schema->components([
            Section::make('Identificazione casella')
                ->icon('heroicon-o-at-symbol')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Etichetta')
                        ->placeholder('Es. PEC ufficiale, Google privacy')
                        ->required()
                        ->maxLength(255),
                    Select::make('company_id')
                        ->label('Società')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible($withCompany && ! Filament::hasTenancy()),
                    TextInput::make('email_address')
                        ->label('Indirizzo gestito')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->trim(),
                    Toggle::make('is_pec')
                        ->label('È una PEC')
                        ->default(false)
                        ->inline(false),
                    Toggle::make('is_active')
                        ->label('Lettura automatica attiva')
                        ->default(true)
                        ->inline(false),
                ]),

            Section::make('Autenticazione')
                ->icon('heroicon-o-key')
                ->columns(2)
                ->schema([
                    Select::make('auth_type')
                        ->label('Metodo')
                        ->required()
                        ->live()
                        ->default('password')
                        ->options([
                            'password' => 'Password / password per programmi di posta',
                            'oauth2' => 'OAuth 2.0 (Google, Microsoft)',
                        ]),
                    Select::make('provider')
                        ->label('Provider OAuth')
                        ->options(['google' => 'Google', 'microsoft' => 'Microsoft'])
                        ->visible(fn (Get $get) => $get('auth_type') === 'oauth2')
                        ->requiredIf('auth_type', 'oauth2'),
                ]),

            Section::make('Posta in arrivo (IMAP)')
                ->icon('heroicon-o-server')
                ->columns(2)
                ->schema([
                    TextInput::make('imap_host')
                        ->label('Host IMAP')
                        ->placeholder('es. imaps.pec.aruba.it, mbox.cert.legalmail.it, imap.gmail.com')
                        ->required()
                        ->maxLength(255)
                        ->trim()
                        ->live(onBlur: true),
                    TextInput::make('imap_port')->label('Porta')->numeric()->default(993)->required(),
                    Select::make('imap_encryption')
                        ->label('Crittografia')
                        ->default('ssl')
                        ->options([
                            'ssl' => 'SSL / TLS (993)',
                            'tls' => 'STARTTLS (143)',
                            'none' => 'Nessuna',
                        ]),
                    TextInput::make('imap_username')->label('Username IMAP')->required()->maxLength(255)->trim(),
                    TextInput::make('imap_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('auth_type') === 'password')
                        ->requiredIf('auth_type', 'password')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Per le PEC Aruba usa la password dedicata ai programmi di posta. Resta cifrata nel database.'),
                ]),

            Section::make('Posta in uscita (SMTP)')
                ->icon('heroicon-o-paper-airplane')
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('smtp_host')->label('Host SMTP')->placeholder('es. smtps.pec.aruba.it')->maxLength(255)->trim(),
                    TextInput::make('smtp_port')->label('Porta')->numeric()->default(465),
                    Select::make('smtp_encryption')
                        ->label('Crittografia')
                        ->default('ssl')
                        ->options(['ssl' => 'SSL / TLS (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Nessuna']),
                    TextInput::make('smtp_username')->label('Username SMTP')->maxLength(255)->trim(),
                    TextInput::make('smtp_password')
                        ->label('Password SMTP')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrated(fn ($state) => filled($state)),
                ]),

            Section::make('Gmail / Google Workspace con password')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn (Get $get) => $get('auth_type') === 'password' && str_contains(mb_strtolower((string) $get('imap_host')), 'gmail'))
                ->schema([
                    Text::make(
                        'Google non accetta più la password normale per IMAP: serve una "Password per le app" di 16 caratteri '
                        .'(verifica in due passaggi attiva) e IMAP abilitato in Gmail. In alternativa scegli OAuth 2.0.'
                    )->color('warning'),
                ]),

            Section::make('Token OAuth 2.0')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->visible(fn (Get $get) => $get('auth_type') === 'oauth2')
                ->description('Incolla i token ottenuti dal consenso del provider: vengono rinnovati con il refresh token alla scadenza.')
                ->schema([
                    TextInput::make('access_token')->label('Access token')->password()->revealable()->dehydrated(fn ($state) => filled($state)),
                    TextInput::make('refresh_token')->label('Refresh token')->password()->revealable()->dehydrated(fn ($state) => filled($state)),
                    DateTimePicker::make('token_expires_at')->label('Scadenza access token')->seconds(false),
                ]),
        ]);
    }
}
