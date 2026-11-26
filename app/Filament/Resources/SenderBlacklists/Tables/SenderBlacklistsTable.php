<?php

namespace App\Filament\Resources\SenderBlacklists\Tables;

use App\Models\SenderBlacklist;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SenderBlacklistsTable
{
    public static function configure(Table $table): Table
    {
        $editable = fn (SenderBlacklist $record) => $record->company_id !== null || auth()->user()?->isSuperAdmin();

        return $table
            ->defaultSort('pattern')
            ->columns([
                TextColumn::make('match_type')->label('Su')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === SenderBlacklist::SUBJECT ? 'Oggetto' : 'Mittente')
                    ->color(fn (?string $state) => $state === SenderBlacklist::SUBJECT ? 'warning' : 'gray'),
                TextColumn::make('pattern')->label('Indirizzo, dominio o testo')->searchable(),
                TextColumn::make('note')->label('Note')->searchable(),
                TextColumn::make('company.name')->label('Società')->placeholder('Tutte'),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
            ])
            ->filters([
                SelectFilter::make('match_type')->label('Su')->options([SenderBlacklist::SENDER => 'Mittente', SenderBlacklist::SUBJECT => 'Oggetto'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        SenderBlacklist::SUBJECT => $query->where('match_type', SenderBlacklist::SUBJECT),
                        SenderBlacklist::SENDER => $query->where(fn ($q) => $q->whereNull('match_type')->orWhere('match_type', SenderBlacklist::SENDER)),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make()->visible($editable),
                DeleteAction::make()->visible($editable),
            ]);
    }
}
