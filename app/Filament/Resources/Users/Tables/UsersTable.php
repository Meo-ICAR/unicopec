<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->sortable()->searchable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('role')->label('Ruolo')->badge()->sortable()
                    ->formatStateUsing(fn (?string $state) => UserForm::roles()[$state] ?? ($state === 'super_admin' ? 'Super admin' : $state))
                    ->color(fn (?string $state) => match ($state) {
                        'super_admin' => 'danger', 'admin' => 'warning', default => 'gray'
                    }),
                TextColumn::make('created_at')->label('Creato il')->dateTime('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')->label('Ruolo')->options(fn () => UserForm::roles()),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
