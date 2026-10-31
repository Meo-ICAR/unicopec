<?php

namespace App\Filament\Resources\SenderBlacklists\Tables;

use App\Models\SenderBlacklist;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SenderBlacklistsTable
{
    public static function configure(Table $table): Table
    {
        $editable = fn (SenderBlacklist $record) => $record->company_id !== null || auth()->user()?->isSuperAdmin();

        return $table
            ->defaultSort('pattern')
            ->columns([
                TextColumn::make('pattern')->label('Indirizzo o dominio')->searchable(),
                TextColumn::make('note')->label('Note')->searchable(),
                TextColumn::make('company.name')->label('Società')->placeholder('Tutte'),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->visible($editable),
                DeleteAction::make()->visible($editable),
            ]);
    }
}
