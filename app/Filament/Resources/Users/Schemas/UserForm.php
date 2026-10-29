<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    /** @return array<string, string> */
    public static function roles(): array
    {
        $roles = ['admin' => 'Admin', 'user' => 'Utente', 'employee' => 'Dipendente', 'fornitore' => 'Fornitore'];

        return auth()->user()?->isSuperAdmin() ? ['super_admin' => 'Super admin'] + $roles : $roles;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Utente')
                ->icon('heroicon-o-user')
                ->description('L\'utente appartiene alla società aperta e vede solo quella.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nome')->required()->maxLength(255),
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                        ->unique(User::class, 'email', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at')),
                    Select::make('role')->label('Ruolo')->options(fn () => self::roles())->default('user')->required()
                        ->helperText('Gli admin modificano ed eliminano le email e gestiscono gli utenti della società; il super admin vede tutte le società.'),
                    TextInput::make('password')->label('Password')->password()->revealable()->minLength(8)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Lascia vuoto per non cambiarla.' : null),
                ]),
        ]);
    }
}
