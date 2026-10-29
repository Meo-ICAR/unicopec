<?php

namespace App\Filament\Resources\MailAccounts\Pages;

use App\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMailAccount extends EditRecord
{
    protected static string $resource = MailAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
