<?php

namespace App\Filament\Resources\IncomingEmails\Schemas;

use App\Enums\EmailCategory;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncomingEmailForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Smistamento')
                ->icon('heroicon-o-arrows-right-left')
                ->description('Qui correggi la classificazione e il referente scelti automaticamente.')
                ->columns(2)
                ->schema([
                    Select::make('classification')
                        ->label('Classificazione')
                        ->options(EmailCategory::options())
                        ->searchable(),
                    Select::make('related_id')
                        ->label('Referente')
                        ->options(fn () => Employee::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(
                            fn (Employee $e) => [$e->id => $e->name.($e->employee_roles ? ' — '.implode(', ', $e->employee_roles) : '')]
                        )->all())
                        ->searchable()
                        ->placeholder('Nessun referente'),
                    Toggle::make('is_read')->label('Letta')->inline(false),
                ]),

            Section::make('Messaggio')
                ->icon('heroicon-o-envelope-open')
                ->columns(2)
                ->schema([
                    TextInput::make('subject')->label('Oggetto')->disabled()->columnSpanFull(),
                    TextInput::make('from_email')->label('Da')->disabled(),
                    TextInput::make('from_name')->label('Mittente')->disabled(),
                    TextInput::make('received_at')->label('Ricevuta il')->disabled(),
                    TextInput::make('mailAccount.email_address')->label('Casella')->disabled()->formatStateUsing(fn ($record) => $record?->mailAccount?->email_address),
                    Textarea::make('body_text')->label('Testo')->rows(10)->disabled()->columnSpanFull(),
                ]),
        ]);
    }
}
