<?php

namespace App\Filament\Resources\IncomingEmails\Pages;

use App\Filament\Resources\IncomingEmails\IncomingEmailResource;
use App\Filament\Widgets\PecStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListIncomingEmails extends ListRecords
{
    protected static string $resource = IncomingEmailResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PecStatsOverview::class];
    }
}
