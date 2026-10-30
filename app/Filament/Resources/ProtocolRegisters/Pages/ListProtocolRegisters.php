<?php

namespace App\Filament\Resources\ProtocolRegisters\Pages;

use App\Filament\Resources\ProtocolRegisters\ProtocolRegisterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProtocolRegisters extends ListRecords
{
    protected static string $resource = ProtocolRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
