<?php

namespace App\Filament\Resources\SenderBlacklists\Pages;

use App\Filament\Resources\SenderBlacklists\SenderBlacklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSenderBlacklists extends ListRecords
{
    protected static string $resource = SenderBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
