<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Employee;
use App\Services\Archive\ArchiveService;
use App\Services\Archive\FakeArchiveService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ArchiveService::class, fn () => match (config('pec.archive.driver')) {
            'fake' => new FakeArchiveService,
            default => throw new \InvalidArgumentException('Servizio di archiviazione sconosciuto: '.config('pec.archive.driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias per i collegamenti polimorfici (incoming_emails.related_type): il referente di un'email è un dipendente/consulente o un cliente.
        Relation::morphMap(['employee' => Employee::class, 'client' => Client::class]);

        // Le azioni di Filament mostrano solo l'icona; l'etichetta resta come tooltip.
        $iconOnly = fn (Action $action) => $action->iconButton()->tooltip(fn () => $action->getLabel());
        Table::configureUsing(fn (Table $table) => $table->modifyUngroupedRecordActionsUsing($iconOnly));
        // Tutte le colonne si ordinano; quelle calcolate (senza colonna nel database) dichiarano ->sortable(false).
        Column::configureUsing(fn (Column $column) => $column->sortable());
        CreateAction::configureUsing($iconOnly);
        DeleteAction::configureUsing($iconOnly);

        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [GoogleExtendSocialite::class, 'handle']);
    }
}
