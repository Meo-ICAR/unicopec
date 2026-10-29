<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BpmBridgeController extends Controller
{
    /**
     * Accesso (single sign-on) proveniente da UnicoBPM: il token si verifica su UnicoBPM, che deve rispondere `valid: true`
     * per quell'email. Solo HTTPS, il token non finisce nei log, la sessione si rigenera dopo il login e ogni rifiuto è un 403 generico.
     */
    public function handle(Request $request, string $subjectId): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'user_email' => ['required', 'email', 'max:255'],
        ]);

        $bpmBaseUrl = rtrim((string) config('services.bpm.url'), '/');

        if (! str_starts_with($bpmBaseUrl, 'https://')) {
            Log::warning('BPM bridge: URL di verifica non HTTPS, richiesta rifiutata.');
            abort(403);
        }

        try {
            $response = Http::asJson()->timeout(8)->connectTimeout(4)
                ->post("{$bpmBaseUrl}/api/verify-token", ['token' => $validated['token'], 'email' => $validated['user_email']]);
        } catch (\Throwable) {
            Log::warning('BPM bridge: verifica token fallita per errore di rete.', ['email' => $validated['user_email']]);
            abort(403);
        }

        if ($response->failed() || $response->json('valid') !== true) {
            Log::warning('BPM bridge: token non valido o scaduto.', ['email' => $validated['user_email'], 'subject_id' => $subjectId, 'ip' => $request->ip()]);
            abort(403, 'Token BPM non valido o scaduto.');
        }

        $user = User::query()->where('email', $validated['user_email'])->first();

        if (! $user || ! $user->canAccessPanel(filament()->getPanel('admin'))) {
            Log::warning('BPM bridge: utente senza accesso al pannello.', ['email' => $validated['user_email'], 'ip' => $request->ip()]);
            abort(403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
