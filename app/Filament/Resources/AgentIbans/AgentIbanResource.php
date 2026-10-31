<?php

namespace App\Filament\Resources\AgentIbans;

use App\Filament\Resources\AgentIbans\Pages\ListAgentIbans;
use App\Services\Mail\IbanAlignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\Fornitore;

/**
 * IBAN degli agenti: l'IBAN in scheda, quello dell'ultima fattura e lo stato di verifica. Qui una persona conferma un IBAN dopo aver richiamato
 * l'agente al numero già in anagrafica e raccolto il documento della banca: da quel momento l'agente risulta allineato e UnicoBPM smette di scrivere al contabile.
 */
class AgentIbanResource extends Resource
{
    protected static ?string $model = Fornitore::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'IBAN agenti';

    protected static ?string $modelLabel = 'Agente';

    protected static ?string $pluralModelLabel = 'IBAN agenti';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 8;

    protected static bool $isScopedToTenant = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canManageEmails();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()->when($tenant, fn (Builder $q) => $q->where('company_id', $tenant->getKey()))
            ->where(fn ($q) => $q->whereNotNull('iban')->orWhereIn('id', app(IbanAlignment::class)->pending($tenant?->getKey())->pluck('fornitore_id')));
    }

    public static function table(Table $table): Table
    {
        $pending = fn (Fornitore $record) => app(IbanAlignment::class)->pending()->firstWhere('fornitore_id', $record->id);

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Agente')->searchable(),
                TextColumn::make('iban')->label('IBAN in scheda')->placeholder('—')->searchable()->copyable(),
                TextColumn::make('iban_fattura')->label('IBAN ultima fattura')->sortable(false)->state(fn (Fornitore $r) => $pending($r)['iban_fattura'] ?? null)->placeholder('—'),
                TextColumn::make('stato')->label('Stato')->sortable(false)->badge()
                    ->state(fn (Fornitore $r) => match ($pending($r)['motivo'] ?? null) {
                        IbanAlignment::MISSING => 'IBAN non in scheda',
                        IbanAlignment::CHANGED => 'IBAN variato',
                        IbanAlignment::UNVERIFIED => 'Da verificare',
                        default => 'Allineato',
                    })
                    ->color(fn (string $state) => match ($state) { 'Allineato' => 'success', 'IBAN variato' => 'danger', default => 'warning' }),
                TextColumn::make('iban_verified_at')->label('Verificato il')->dateTime('d/m/Y')->placeholder('non verificato'),
                TextColumn::make('iban_updated_at')->label('Variato il')->dateTime('d/m/Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Filter::make('da_allineare')->label('Da allineare')->default()
                    ->query(fn (Builder $query) => $query->whereIn('id', app(IbanAlignment::class)->pending(Filament::getTenant()?->getKey())->pluck('fornitore_id'))),
            ])
            ->recordActions([
                Action::make('conferma')
                    ->label('Conferma IBAN')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Fornitore $r) => $pending($r) !== null)
                    ->modalDescription('Conferma l\'IBAN dell\'ultima fattura come IBAN dell\'agente. Fallo solo dopo la richiamata al numero già in anagrafica e il documento della banca.')
                    ->schema(fn (Fornitore $r) => [
                        TextInput::make('iban')->label('IBAN')->default($pending($r)['iban_fattura'] ?? $r->iban)->required()->maxLength(34),
                        Checkbox::make('richiamata')->label('Ho richiamato l\'agente al numero già in anagrafica e ho il documento della banca')->accepted()->required(),
                    ])
                    ->action(function (Fornitore $r, array $data) {
                        try {
                            app(IbanAlignment::class)->align($r, $data['iban'], auth()->id());
                            Notification::make()->title("IBAN di {$r->name} confermato")->success()->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAgentIbans::route('/')];
    }
}
