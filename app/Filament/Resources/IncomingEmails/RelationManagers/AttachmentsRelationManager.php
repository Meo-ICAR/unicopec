<?php

namespace App\Filament\Resources\IncomingEmails\RelationManagers;

use App\Models\IncomingEmail;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Gli allegati di un'email, scaricabili solo da chi ha fatto l'accesso (il file non è esposto da un URL pubblico). */
class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Allegati';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('collection_name', IncomingEmail::ATTACHMENTS))
            ->defaultSort('id')
            ->emptyStateHeading('Nessun allegato')
            ->columns([
                TextColumn::make('file_name')->label('File')->searchable(),
                TextColumn::make('mime_type')->label('Tipo'),
                TextColumn::make('size')->label('Dimensione')->formatStateUsing(fn ($state) => number_format($state / 1024, 1, ',', '.').' KB'),
                TextColumn::make('created_at')->label('Scaricato il')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('scarica')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Media $record) => response()->download($record->getPath(), $record->file_name)),
            ]);
    }
}
