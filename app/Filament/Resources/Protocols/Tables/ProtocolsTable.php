<?php

namespace App\Filament\Resources\Protocols\Tables;

use App\Models\Protocol;
use App\Models\ProtocolRegister;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProtocolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('identifier')->label('Protocollo')->searchable()->weight('bold'),
                TextColumn::make('type')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => $state === Protocol::SMISTAMENTO ? 'Smistamento' : 'Presa in carico'),
                TextColumn::make('email.subject')->label('Email')->limit(60)->searchable(),
                TextColumn::make('role')->label('Funzione'),
                TextColumn::make('employee.name')->label('Persona')->placeholder('—'),
                TextColumn::make('protocolled_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('protocol_register_id')->label('Registro')
                    ->options(fn () => ProtocolRegister::query()->when(Filament::getTenant(), fn ($q, $t) => $q->where('company_id', $t->getKey()))->orderBy('code')->pluck('code', 'id')->all()),
                SelectFilter::make('year')->label('Anno')->options(fn () => Protocol::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
                SelectFilter::make('type')->label('Tipo')->options([Protocol::SMISTAMENTO => 'Smistamento', Protocol::PRESA_IN_CARICO => 'Presa in carico']),
            ]);
    }
}
