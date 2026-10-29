<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Anagrafica')
                ->icon('heroicon-o-building-library')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Ragione sociale')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('vat_number')->label('Partita IVA')->maxLength(50),
                    TextInput::make('tax_code')->label('Codice fiscale')->maxLength(50),
                    TextInput::make('address')->label('Indirizzo')->maxLength(255)->columnSpanFull(),
                    TextInput::make('phone')->label('Telefono')->tel()->maxLength(50),
                    Toggle::make('is_active')->label('Attiva')->default(true)->inline(false),
                ]),

            Section::make('Contatti')
                ->icon('heroicon-o-envelope')
                ->columns(2)
                ->schema([
                    TextInput::make('email')->label('Email')->email()->maxLength(255),
                    TextInput::make('pec')->label('PEC')->email()->maxLength(255),
                    TextInput::make('dpo_email')->label('Email DPO / RPD')->email()->maxLength(255),
                    TextInput::make('email_it')->label('Email referente IT')->email()->maxLength(255),
                    TextInput::make('referee')->label('Referente')->maxLength(255),
                    TextInput::make('email_referee')->label('Email referente')->email()->maxLength(255),
                ]),

            Section::make('Note')
                ->collapsed()
                ->schema([
                    Textarea::make('notes')->label('Note')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }
}
