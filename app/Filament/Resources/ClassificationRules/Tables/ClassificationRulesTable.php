<?php

namespace App\Filament\Resources\ClassificationRules\Tables;

use App\Models\ClassificationRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassificationRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('priority')->label('Priorità')->sortable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('category')->label('Categoria')->badge(),
                TextColumn::make('company.name')->label('Società')->placeholder('Tutte'),
                TextColumn::make('operator')->label('Condizioni')->formatStateUsing(fn (string $state) => $state === 'and' ? 'Tutte' : 'Una qualsiasi'),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (ClassificationRule $record) => $record->company_id !== null || auth()->user()?->isSuperAdmin()),
                DeleteAction::make()->visible(fn (ClassificationRule $record) => $record->company_id !== null || auth()->user()?->isSuperAdmin()),
            ]);
    }
}
