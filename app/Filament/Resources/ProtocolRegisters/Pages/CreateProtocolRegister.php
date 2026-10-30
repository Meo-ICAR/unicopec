<?php

namespace App\Filament\Resources\ProtocolRegisters\Pages;

use App\Filament\Resources\ProtocolRegisters\ProtocolRegisterResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateProtocolRegister extends CreateRecord
{
    protected static string $resource = ProtocolRegisterResource::class;

    /** @var array<int, string> */
    private array $roles = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roles = (array) ($data['roles'] ?? []);
        unset($data['roles']);
        $data['company_id'] = Filament::getTenant()?->getKey();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles($this->roles);
    }
}
