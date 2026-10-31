<?php

namespace App\Filament\Resources\SenderBlacklists;

use App\Filament\Resources\SenderBlacklists\Pages\CreateSenderBlacklist;
use App\Filament\Resources\SenderBlacklists\Pages\EditSenderBlacklist;
use App\Filament\Resources\SenderBlacklists\Pages\ListSenderBlacklists;
use App\Filament\Resources\SenderBlacklists\Schemas\SenderBlacklistForm;
use App\Filament\Resources\SenderBlacklists\Tables\SenderBlacklistsTable;
use App\Models\SenderBlacklist;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Mittenti esclusi dall'importo delle email (newsletter, social, notifiche). Le voci senza società valgono per tutte. */
class SenderBlacklistResource extends Resource
{
    protected static ?string $model = SenderBlacklist::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Mittenti esclusi';

    protected static ?string $modelLabel = 'Mittente escluso';

    protected static ?string $pluralModelLabel = 'Mittenti esclusi';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 7;

    // Le voci globali non hanno società: lo scoping automatico del tenant le nasconderebbe, quindi il filtro è esplicito.
    protected static bool $isScopedToTenant = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canManageEmails();
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()->when($tenant, fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('company_id')->orWhere('company_id', $tenant->getKey())));
    }

    /** Le voci per tutte le società le gestisce solo il super admin. */
    public static function canEdit(Model $record): bool
    {
        return $record->company_id !== null || (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return SenderBlacklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SenderBlacklistsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSenderBlacklists::route('/'),
            'create' => CreateSenderBlacklist::route('/create'),
            'edit' => EditSenderBlacklist::route('/{record}/edit'),
        ];
    }
}
