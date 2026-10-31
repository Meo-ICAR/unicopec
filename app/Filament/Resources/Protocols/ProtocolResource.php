<?php

namespace App\Filament\Resources\Protocols;

use App\Filament\Resources\Protocols\Pages\ListProtocols;
use App\Filament\Resources\Protocols\Tables\ProtocolsTable;
use App\Models\Protocol;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Consultazione dei protocolli della società aperta: si creano solo dallo smistamento e dalla presa in carico, e non si modificano. */
class ProtocolResource extends Resource
{
    protected static ?string $model = Protocol::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationLabel = 'Protocolli';

    protected static ?string $modelLabel = 'Protocollo';

    protected static ?string $pluralModelLabel = 'Protocolli';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 6;

    // Il protocollo non ha la società: si risale dal registro.
    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()->when($tenant, fn (Builder $q) => $q->whereHas('register', fn ($r) => $r->where('company_id', $tenant->getKey())));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ProtocolsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListProtocols::route('/')];
    }
}
