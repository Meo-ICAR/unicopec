<?php

namespace App\Filament\Resources\ProtocolRegisters\Tables;

use App\Models\ProtocolRegister;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProtocolRegistersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Sigla')->sortable()->searchable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('roles')->label('Funzioni')->sortable(false)->state(fn (ProtocolRegister $record) => $record->roleNames())->badge(),
                TextColumn::make('protocols_count')->label('Protocolli')->counts('protocols'),
                IconColumn::make('is_active')->label('Attivo')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
