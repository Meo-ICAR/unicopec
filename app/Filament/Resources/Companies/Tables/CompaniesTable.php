<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ragione sociale')->sortable()->searchable(),
                TextColumn::make('vat_number')->label('P.IVA')->searchable()->toggleable(),
                TextColumn::make('pec')->label('PEC')->searchable()->toggleable(),
                TextColumn::make('mail_accounts_count')->label('Caselle')->counts('mailAccounts')->sortable(),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
                TextColumn::make('created_at')->label('Creata il')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
