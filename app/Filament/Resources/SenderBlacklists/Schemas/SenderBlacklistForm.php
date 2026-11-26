<?php

namespace App\Filament\Resources\SenderBlacklists\Schemas;

use App\Models\SenderBlacklist;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SenderBlacklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Email da escludere')->columns(2)->schema([
                Select::make('match_type')->label('Escludi in base a')->options([SenderBlacklist::SENDER => 'Mittente', SenderBlacklist::SUBJECT => 'Oggetto'])
                    ->default(SenderBlacklist::SENDER)->required()->native(false)->live()->columnSpanFull(),
                TextInput::make('pattern')->required()->maxLength(255)->columnSpanFull()
                    ->label(fn (Get $get) => $get('match_type') === SenderBlacklist::SUBJECT ? 'Testo nell\'oggetto' : 'Indirizzo o dominio')
                    ->placeholder(fn (Get $get) => $get('match_type') === SenderBlacklist::SUBJECT ? 'Autenticazione a multi fattore' : 'microsoft.com')
                    ->dehydrateStateUsing(fn (?string $state, Get $get) => $get('match_type') === SenderBlacklist::SUBJECT ? SenderBlacklist::normalizeSubject((string) $state) : SenderBlacklist::normalize((string) $state))
                    ->helperText(fn (Get $get) => $get('match_type') === SenderBlacklist::SUBJECT
                        ? 'Si escludono le email il cui oggetto contiene questo testo (senza distinguere maiuscole e minuscole); con * si può usare un modello (codice * accesso). Le email già importate restano dove sono.'
                        : 'Un dominio (microsoft.com) esclude anche i sottodomini (e-mail.microsoft.com); un indirizzo (news@x.it) solo quello; si può usare * (no-reply@*). Le email già importate restano dove sono.'),
                TextInput::make('note')->label('Note')->maxLength(255)->columnSpanFull(),
                Toggle::make('for_all')->label('Vale per tutte le società')->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->dehydrated(false)->default(false)->afterStateHydrated(fn (Toggle $component, ?SenderBlacklist $record) => $component->state($record !== null && $record->company_id === null)),
                Toggle::make('is_active')->label('Attiva')->default(true),
            ]),
        ]);
    }
}
