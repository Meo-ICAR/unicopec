<?php

namespace App\Filament\Resources\SenderBlacklists\Pages;

use App\Filament\Resources\SenderBlacklists\SenderBlacklistResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditSenderBlacklist extends EditRecord
{
    protected static string $resource = SenderBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isSuperAdmin()) {
            $data['company_id'] = ($this->data['for_all'] ?? false) ? null : ($this->record->company_id ?? Filament::getTenant()?->getKey());
        }

        return $data;
    }
}
