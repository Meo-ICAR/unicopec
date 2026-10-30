<?php

namespace App\Filament\Resources\IncomingEmails\RelationManagers;

use App\Models\Protocol;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** I protocolli di un'email: smistamento e presa in carico. */
class ProtocolsRelationManager extends RelationManager
{
    protected static string $relationship = 'protocols';

    protected static ?string $title = 'Protocolli';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('identifier')->label('Protocollo')->weight('bold'),
            TextColumn::make('type')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => $state === Protocol::SMISTAMENTO ? 'Smistamento' : 'Presa in carico'),
            TextColumn::make('role')->label('Funzione'),
            TextColumn::make('employee.name')->label('Persona')->placeholder('—'),
            TextColumn::make('protocolled_at')->label('Data')->dateTime('d/m/Y H:i'),
        ]);
    }
}
