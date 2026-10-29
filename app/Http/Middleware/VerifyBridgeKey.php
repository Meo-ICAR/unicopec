<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Autentica le chiamate di UnicoBPM con la chiave condivisa nell'intestazione X-Api-Key (BPM_BRIDGE_API_KEY).
 * Senza chiave configurata l'API è spenta (503): mai aperta per dimenticanza.
 */
class VerifyBridgeKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.bpm.key');

        if ($expected === '') {
            Log::error('API bridge disabilitata: BPM_BRIDGE_API_KEY non configurata.');

            throw new HttpException(503, 'Servizio non configurato.');
        }

        if (! hash_equals($expected, (string) $request->header('X-Api-Key', ''))) {
            Log::warning('Accesso all\'API bridge rifiutato: chiave mancante o non valida.', ['path' => $request->path()]);

            throw new HttpException(401, 'Non autorizzato.');
        }

        return $next($request);
    }
}
