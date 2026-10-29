<?php

namespace App\Filament\Resources\IncomingEmails\Tables;

use App\Enums\EmailCategory;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Services\Bpm\BpmEvents;
use App\Services\Mail\EmailClassifier;
use App\Services\Mail\EmailRouter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IncomingEmailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['related', 'company', 'mailAccount']))
            ->columns([
                TextColumn::make('received_at')->label('Ricevuta')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('from_email')->label('Mittente')->searchable()->limit(32)->tooltip(fn ($record) => $record->from_name),
                TextColumn::make('subject')->label('Oggetto')->searchable()->limit(60)->tooltip(fn ($record) => $record->subject)->wrap(),
                TextColumn::make('classification')
                    ->label('Classificazione')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => EmailCategory::tryFrom((string) $state)?->getLabel() ?? ($state ?: 'Da classificare'))
                    ->color(fn (?string $state) => EmailCategory::tryFrom((string) $state)?->getColor() ?? 'gray'),
                TextColumn::make('related.name')->label('Referente')->placeholder('—')->searchable(),
                TextColumn::make('company.name')->label('Società')->toggleable(),
                TextColumn::make('mailAccount.email_address')->label('Casella')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_read')->label('Letta')->boolean(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                SelectFilter::make('classification')->label('Classificazione')->options(EmailCategory::options()),
                SelectFilter::make('company_id')->label('Società')->relationship('company', 'name'),
                SelectFilter::make('mail_account_id')->label('Casella')->relationship('mailAccount', 'email_address'),
                Filter::make('senza_referente')->label('Senza referente')->query(fn (Builder $q) => $q->whereNull('related_id')->whereNotIn('classification', [EmailCategory::ProviderNotification->value, EmailCategory::NonRilevante->value])),
                Filter::make('da_classificare')->label('Da classificare')->query(fn (Builder $q) => $q->whereNull('classification')),
                Filter::make('non_lette')->label('Non lette')->query(fn (Builder $q) => $q->where('is_read', false)),
            ])
            ->recordActions([
                Action::make('classifica')
                    ->label('Classifica con AI')
                    ->icon('heroicon-o-sparkles')
                    ->action(function (IncomingEmail $record): void {
                        try {
                            $result = app(EmailClassifier::class)->classify($record);
                        } catch (\Throwable $e) {
                            Notification::make()->title('Classificazione non riuscita')->body(mb_substr($e->getMessage(), 0, 200))->danger()->send();

                            return;
                        }

                        $record->forceFill(['classification' => $result->category->value])->save();
                        BpmEvents::dispatchReceived($record, $result);
                        Notification::make()->title($result->category->getLabel().' — urgenza '.$result->urgency)->body($result->summary)->success()->send();
                    }),
                Action::make('smista')
                    ->label('Smista')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (IncomingEmail $record) => EmailCategory::tryFrom((string) $record->classification)?->isRoutable() === true)
                    ->action(function (IncomingEmail $record): void {
                        $found = app(EmailRouter::class)->route($record, EmailCategory::from($record->classification));

                        if (! $found) {
                            Notification::make()->title('Nessun referente trovato')->warning()->send();

                            return;
                        }

                        $record->forceFill(['related_type' => 'employee', 'related_id' => $found['employee']->id])->save();
                        BpmEvents::dispatchAssigned($record, $found['employee'], $found['role']);
                        Notification::make()->title('Assegnata a '.$found['employee']->name)->body("Ruolo {$found['role']} ({$found['via']})")->success()->send();
                    }),
                EditAction::make()->label('Apri'),
            ]);
    }
}
