<?php

namespace App\Filament\Resources\MailAccounts;

use App\Filament\Resources\MailAccounts\Pages\CreateMailAccount;
use App\Filament\Resources\MailAccounts\Pages\EditMailAccount;
use App\Filament\Resources\MailAccounts\Pages\ListMailAccounts;
use App\Filament\Resources\MailAccounts\Schemas\MailAccountForm;
use App\Filament\Resources\MailAccounts\Tables\MailAccountsTable;
use App\Models\MailAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MailAccountResource extends Resource
{
    protected static ?string $model = MailAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-at-symbol';

    protected static ?string $navigationLabel = 'Caselle email / PEC';

    protected static ?string $modelLabel = 'Casella';

    protected static ?string $pluralModelLabel = 'Caselle email / PEC';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MailAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailAccounts::route('/'),
            'create' => CreateMailAccount::route('/create'),
            'edit' => EditMailAccount::route('/{record}/edit'),
        ];
    }
}
