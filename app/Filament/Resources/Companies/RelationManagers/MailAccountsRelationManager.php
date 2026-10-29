<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\MailAccounts\Schemas\MailAccountForm;
use App\Filament\Resources\MailAccounts\Tables\MailAccountsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MailAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'mailAccounts';

    protected static ?string $title = 'Caselle email / PEC';

    public function form(Schema $schema): Schema
    {
        return MailAccountForm::configure($schema, withCompany: false);
    }

    public function table(Table $table): Table
    {
        return MailAccountsTable::configure($table, withCompany: false)
            ->headerActions([CreateAction::make()]);
    }
}
