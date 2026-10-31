<?php

namespace App\Filament\Resources\SenderBlacklists\Schemas;

use App\Models\SenderBlacklist;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SenderBlacklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mittente da escludere')->columns(2)->schema([
                TextInput::make('pattern')->label('Indirizzo o dominio')->required()->maxLength(255)->columnSpanFull()
                    ->placeholder('microsoft.com')
                    ->dehydrateStateUsing(fn (?string $state) => SenderBlacklist::normalize((string) $state))
                    ->helperText('Un dominio (microsoft.com) esclude anche i sottodomini (e-mail.microsoft.com); un indirizzo (news@x.it) solo quello; si può usare * (no-reply@*). Le email già importate restano dove sono.'),
                TextInput::make('note')->label('Note')->maxLength(255)->columnSpanFull(),
                Toggle::make('for_all')->label('Vale per tutte le società')->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->dehydrated(false)->default(false)->afterStateHydrated(fn (Toggle $component, ?SenderBlacklist $record) => $component->state($record !== null && $record->company_id === null)),
                Toggle::make('is_active')->label('Attiva')->default(true),
            ]),
        ]);
    }
}
