<?php

namespace App\Filament\Resources\IncomingEmails\Tables;

use App\Enums\EmailCategory;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Services\Mail\EmailClassifier;
use App\Services\Mail\EmailForwarder;
use App\Services\Mail\EmailPipeline;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['related', 'company', 'mailAccount', 'events']))
            ->columns([
                TextColumn::make('received_at')->label('Ricevuta')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('from_email')->label('Mittente')->searchable()->limit(30)->tooltip(fn ($record) => $record->from_name),
                TextColumn::make('subject')->label('Oggetto')->searchable()->limit(55)->tooltip(fn ($record) => $record->subject)->wrap(),
                TextColumn::make('classification')
                    ->label('Classificazione')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => EmailCategory::tryFrom((string) $state)?->getLabel() ?? ($state ?: 'Da classificare'))
                    ->color(fn (?string $state) => EmailCategory::tryFrom((string) $state)?->getColor() ?? 'gray'),
                TextColumn::make('related.name')->label('Smistata a')->placeholder('—')->searchable(),
                TextColumn::make('outcome')
                    ->label('Esito')
                    ->badge()
                    ->state(fn (IncomingEmail $record) => $record->outcome()['label'])
                    ->color(fn (IncomingEmail $record) => $record->outcome()['color']),
                TextColumn::make('anomalies')
                    ->label('Anomalie')
                    ->badge()
                    ->state(fn (IncomingEmail $record) => count($record->anomalies()) ?: null)
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->tooltip(fn (IncomingEmail $record) => implode("\n", $record->anomalies()) ?: null)
                    ->placeholder('—'),
                TextColumn::make('company.name')->label('Società')->toggleable(),
                TextColumn::make('mailAccount.email_address')->label('Casella')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_read')->label('Letta')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                Filter::make('con_anomalie')->label('Con anomalie')->query(fn (Builder $query) => $query->withAnomalies()),
                Filter::make('senza_referente')->label('Senza referente')->query(fn (Builder $query) => $query->whereNull('related_id')->whereNotIn('classification', [EmailCategory::ProviderNotification->value, EmailCategory::NonRilevante->value])),
                Filter::make('da_classificare')->label('Da classificare')->query(fn (Builder $query) => $query->whereNull('classification')),
                Filter::make('inoltrate')->label('Inoltrate al referente')->query(fn (Builder $query) => $query->whereHas('events', fn ($e) => $e->where('kind', EmailEvent::FORWARDED)->whereIn('status', ['ok', 'warning']))),
                Filter::make('processo_bpm')->label('Con processo BPM')->query(fn (Builder $query) => $query->whereHas('events', fn ($e) => $e->where('kind', EmailEvent::BPM_EVENT)->where('status', 'ok'))),
                SelectFilter::make('classification')->label('Classificazione')->options(EmailCategory::options()),
                SelectFilter::make('company_id')->label('Società')->relationship('company', 'name'),
                SelectFilter::make('mail_account_id')->label('Casella')->relationship('mailAccount', 'email_address'),
            ])
            ->recordActions([
                self::classifyAction(),
                self::routeAction(),
                self::forwardAction(),
                EditAction::make()->label('Apri'),
            ]);
    }

    public static function classifyAction(): Action
    {
        return Action::make('classifica')
            ->label('Classifica con AI')
            ->icon('heroicon-o-sparkles')
            ->action(function (IncomingEmail $record): void {
                try {
                    $result = app(EmailClassifier::class)->classify($record);
                } catch (\Throwable $e) {
                    EmailEvent::record($record, EmailEvent::ANOMALY, 'error', 'Classificazione non riuscita: '.mb_substr($e->getMessage(), 0, 200));
                    Notification::make()->title('Classificazione non riuscita')->body(mb_substr($e->getMessage(), 0, 200))->danger()->send();

                    return;
                }

                app(EmailPipeline::class)->classified($record, $result);
                Notification::make()->title($result->category->getLabel().' — urgenza '.$result->urgency)->body($result->summary)->success()->send();
            });
    }

    public static function routeAction(): Action
    {
        return Action::make('smista')
            ->label('Smista')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn (IncomingEmail $record) => EmailCategory::tryFrom((string) $record->classification)?->isRoutable() === true)
            ->action(function (IncomingEmail $record): void {
                $category = EmailCategory::from($record->classification);
                $found = app(EmailRouter::class)->route($record, $category);

                if (! $found) {
                    app(EmailPipeline::class)->noReferent($record, $category);
                    Notification::make()->title('Nessun referente trovato')->warning()->send();

                    return;
                }

                app(EmailPipeline::class)->routed($record, $found);
                Notification::make()->title('Assegnata a '.$found['employee']->name)->body("Ruolo {$found['role']} ({$found['via']})")->success()->send();
            });
    }

    public static function forwardAction(): Action
    {
        return Action::make('inoltra')
            ->label('Inoltra al referente')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (IncomingEmail $record) => $record->related_id !== null)
            ->requiresConfirmation()
            ->modalDescription('La PEC con i suoi allegati viene inviata all\'indirizzo email del referente. In simulazione non parte nessuna email vera.')
            ->action(function (IncomingEmail $record): void {
                $result = app(EmailForwarder::class)->forward($record->loadMissing('related', 'mailAccount', 'company'), 'inoltro manuale', force: true);

                Notification::make()->title($result['message'])->color(match ($result['status']) { 'ok' => 'success', 'warning' => 'warning', default => 'danger' })->send();
            });
    }
}
