<?php

namespace App\Filament\Resources\ClassificationRules\Pages;

use App\Filament\Resources\ClassificationRules\ClassificationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClassificationRules extends ListRecords
{
    protected static string $resource = ClassificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
