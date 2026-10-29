<?php

namespace App\Filament\Resources\MailAccounts\Pages;

use App\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMailAccounts extends ListRecords
{
    protected static string $resource = MailAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
