<?php

namespace App\Filament\Resources\IncomingEmails;

use App\Filament\Resources\IncomingEmails\Pages\EditIncomingEmail;
use App\Filament\Resources\IncomingEmails\Pages\ListIncomingEmails;
use App\Filament\Resources\IncomingEmails\RelationManagers\EventsRelationManager;
use App\Filament\Resources\IncomingEmails\Schemas\IncomingEmailForm;
use App\Filament\Resources\IncomingEmails\Tables\IncomingEmailsTable;
use App\Models\IncomingEmail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IncomingEmailResource extends Resource
{
    protected static ?string $model = IncomingEmail::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'PEC ed email ricevute';

    protected static ?string $modelLabel = 'Email';

    protected static ?string $pluralModelLabel = 'PEC ed email ricevute';

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return IncomingEmailForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncomingEmailsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [EventsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomingEmails::route('/'),
            'edit' => EditIncomingEmail::route('/{record}'),
        ];
    }
}
