<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Utenti della società aperta (users.company_id = tenant). Li gestiscono admin e super admin;
 * un admin non vede né modifica i super admin e non può assegnare quel ruolo.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Utenti';

    protected static ?string $modelLabel = 'Utente';

    protected static ?string $pluralModelLabel = 'Utenti';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canManageEmails();
    }

    public static function getEloquentQuery(): Builder
    {
        // Filtro esplicito sulla società aperta, oltre allo scoping automatico del tenant.
        return parent::getEloquentQuery()->when(Filament::getTenant(), fn (Builder $query, $tenant) => $query->where('users.company_id', $tenant->getKey()))->when(! auth()->user()?->isSuperAdmin(), fn (Builder $query) => $query->where('role', '!=', 'super_admin'));
    }

    /** Non ci si elimina da soli. */
    public static function canDelete(Model $record): bool
    {
        return $record->getKey() !== auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
