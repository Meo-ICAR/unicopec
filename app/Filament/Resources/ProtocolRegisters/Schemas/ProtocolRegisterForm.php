<?php

namespace App\Filament\Resources\ProtocolRegisters\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class ProtocolRegisterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Registro')->columns(2)->schema([
                TextInput::make('code')->label('Sigla')->required()->maxLength(10)->alphaDash()
                    ->disabled(fn (?\App\Models\ProtocolRegister $record) => $record?->protocols()->exists())
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper((string) $state))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('company_id', Filament::getTenant()?->getKey()))
                    ->helperText('Compare nel numero, per esempio FIS/2026/000045. Dopo il primo protocollo la sigla non si può più cambiare.'),
                TextInput::make('name')->label('Nome')->required()->maxLength(255),
                Select::make('roles')->label('Funzioni che possono protocollare')->multiple()->searchable()->columnSpanFull()
                    ->options(fn () => DB::table('employee_types')->orderBy('name')->pluck('name', 'name')->all())
                    ->helperText('Una funzione può usare più registri e un registro può servire più funzioni.'),
                Toggle::make('is_active')->label('Attivo')->default(true),
            ]),
        ]);
    }
}
