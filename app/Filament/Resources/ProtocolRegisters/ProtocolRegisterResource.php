<?php

namespace App\Filament\Resources\ProtocolRegisters;

use App\Filament\Resources\ProtocolRegisters\Pages\CreateProtocolRegister;
use App\Filament\Resources\ProtocolRegisters\Pages\EditProtocolRegister;
use App\Filament\Resources\ProtocolRegisters\Pages\ListProtocolRegisters;
use App\Filament\Resources\ProtocolRegisters\Schemas\ProtocolRegisterForm;
use App\Filament\Resources\ProtocolRegisters\Tables\ProtocolRegistersTable;
use App\Models\ProtocolRegister;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/** Registri di protocollo della società aperta, collegati alle funzioni aziendali che possono protocollare in ciascuno. */
class ProtocolRegisterResource extends Resource
{
    protected static ?string $model = ProtocolRegister::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Registri di protocollo';

    protected static ?string $modelLabel = 'Registro';

    protected static ?string $pluralModelLabel = 'Registri di protocollo';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canManageEmails();
    }

    public static function form(Schema $schema): Schema
    {
        return ProtocolRegisterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProtocolRegistersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProtocolRegisters::route('/'),
            'create' => CreateProtocolRegister::route('/create'),
            'edit' => EditProtocolRegister::route('/{record}/edit'),
        ];
    }
}
