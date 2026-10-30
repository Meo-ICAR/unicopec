<?php

namespace App\Filament\Resources\ProtocolRegisters\Pages;

use App\Filament\Resources\ProtocolRegisters\ProtocolRegisterResource;
use Filament\Resources\Pages\EditRecord;

/** Un registro con protocolli non si cancella: si disattiva. */
class EditProtocolRegister extends EditRecord
{
    protected static string $resource = ProtocolRegisterResource::class;

    /** @var array<int, string> */
    private array $roles = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roleNames();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roles = (array) ($data['roles'] ?? []);
        unset($data['roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles($this->roles);
    }
}
