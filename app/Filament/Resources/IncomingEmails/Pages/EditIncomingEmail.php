<?php

namespace App\Filament\Resources\IncomingEmails\Pages;

use App\Filament\Resources\IncomingEmails\IncomingEmailResource;
use App\Filament\Resources\IncomingEmails\Tables\IncomingEmailsTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIncomingEmail extends EditRecord
{
    protected static string $resource = IncomingEmailResource::class;

    protected static ?string $title = 'Email ricevuta';

    protected function getHeaderActions(): array
    {
        return [
            IncomingEmailsTable::classifyAction(),
            IncomingEmailsTable::routeAction(),
            IncomingEmailsTable::forwardAction(),
            IncomingEmailsTable::handoverAction(),
            IncomingEmailsTable::takenAction(),
            DeleteAction::make(),
        ];
    }

    /** Il referente si sceglie tra dipendenti/consulenti: related_type è sempre `employee`. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(auth()->user()?->canManageEmails(), 403);

        $data['related_type'] = filled($data['related_id'] ?? null) ? 'employee' : null;

        return $data;
    }

    /** Chi non può gestire le email vede il form in sola lettura e senza pulsanti di salvataggio. */
    protected function getFormActions(): array
    {
        return auth()->user()?->canManageEmails() ? parent::getFormActions() : [];
    }
}
