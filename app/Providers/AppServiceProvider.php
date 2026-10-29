<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Employee;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias per i collegamenti polimorfici (incoming_emails.related_type): il referente di un'email è un dipendente/consulente o un cliente.
        Relation::morphMap(['employee' => Employee::class, 'client' => Client::class]);

        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [GoogleExtendSocialite::class, 'handle']);
    }
}
