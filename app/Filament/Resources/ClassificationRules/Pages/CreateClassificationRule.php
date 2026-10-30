<?php

namespace App\Filament\Resources\ClassificationRules\Pages;

use App\Filament\Resources\ClassificationRules\ClassificationRuleResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateClassificationRule extends CreateRecord
{
    protected static string $resource = ClassificationRuleResource::class;

    /** Una regola nuova vale per la società aperta; le globali le crea il super admin lasciando la società vuota dalla modifica. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] ??= Filament::getTenant()?->getKey();

        return $data;
    }
}
