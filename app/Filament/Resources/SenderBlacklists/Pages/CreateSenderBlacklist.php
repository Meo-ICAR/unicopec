<?php

namespace App\Filament\Resources\SenderBlacklists\Pages;

use App\Filament\Resources\SenderBlacklists\SenderBlacklistResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSenderBlacklist extends CreateRecord
{
    protected static string $resource = SenderBlacklistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = ($this->data['for_all'] ?? false) && auth()->user()?->isSuperAdmin() ? null : (Filament::getTenant()?->getKey() ?? auth()->user()?->company_id);

        return $data;
    }
}
