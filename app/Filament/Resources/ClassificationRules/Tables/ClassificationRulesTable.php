<?php

namespace App\Filament\Resources\ClassificationRules\Tables;

use App\Filament\Resources\ClassificationRules\ClassificationRuleResource;
use App\Models\ClassificationRule;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassificationRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('priority')->label('Priorità')->sortable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('category')->label('Categoria')->badge(),
                TextColumn::make('company.name')->label('Società')->placeholder('Tutte'),
                TextColumn::make('operator')->label('Condizioni')->formatStateUsing(fn (string $state) => $state === 'and' ? 'Tutte' : 'Una qualsiasi'),
                IconColumn::make('is_active')->label('Attiva')->boolean(),
            ])
            ->recordActions([
                // Una regola comune vale per tutte le società: chi non è super admin ne crea una copia della propria, a parità di priorità prevale quella di società.
                Action::make('personalizza')
                    ->label('Personalizza per la mia società')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (ClassificationRule $record) => $record->company_id === null && ! auth()->user()?->isSuperAdmin() && (Filament::getTenant()?->getKey() ?? auth()->user()?->company_id) !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Crea una copia di questa regola per la tua società, che potrai modificare. La regola comune resta invariata per le altre società.')
                    ->action(function (ClassificationRule $record) {
                        $copy = $record->replicate();
                        $copy->company_id = Filament::getTenant()?->getKey() ?? auth()->user()->company_id;
                        $copy->save();

                        return redirect(ClassificationRuleResource::getUrl('edit', ['record' => $copy]));
                    }),
                EditAction::make()->visible(fn (ClassificationRule $record) => $record->company_id !== null || auth()->user()?->isSuperAdmin()),
                DeleteAction::make()->visible(fn (ClassificationRule $record) => $record->company_id !== null || auth()->user()?->isSuperAdmin()),
            ]);
    }
}
