<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/** Ricorda l'ultima società aperta, così al prossimo accesso si riparte da lì. */
class RememberLastTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if ($user && $tenant && $user->last_company_id !== $tenant->getKey()) {
            $user->forceFill(['last_company_id' => $tenant->getKey()])->save();
        }

        return $next($request);
    }
}
