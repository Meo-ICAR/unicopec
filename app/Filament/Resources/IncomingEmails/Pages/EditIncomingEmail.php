<?php

namespace App\Filament\Resources\IncomingEmails\Pages;

use App\Filament\Resources\IncomingEmails\IncomingEmailResource;
use Filament\Resources\Pages\EditRecord;

class EditIncomingEmail extends EditRecord
{
    protected static string $resource = IncomingEmailResource::class;

    protected static ?string $title = 'Email ricevuta';

    /** Il referente si sceglie tra dipendenti/consulenti: related_type è sempre `employee`. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['related_type'] = filled($data['related_id'] ?? null) ? 'employee' : null;

        return $data;
    }
}
