<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Services\Mail\MicrosoftMailOAuth;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class MicrosoftMailOAuthController extends Controller
{
    /** Porta l'utente al consenso Microsoft per la casella scelta. */
    public function redirect(Request $request, MailAccount $account, MicrosoftMailOAuth $oauth): RedirectResponse
    {
        $state = MicrosoftMailOAuth::newState();
        $request->session()->put('mail_oauth', ['state' => $state, 'account_id' => $account->id]);

        return redirect()->away($oauth->authorizationUrl($account, $state));
    }

    /** Ritorno da Microsoft: scambia il codice con i token, poi torna alla scheda della casella. */
    public function callback(Request $request, MicrosoftMailOAuth $oauth): RedirectResponse|Response
    {
        // Ritorno del consenso amministratore: l'amministratore del tenant non ha un accesso a questo pannello, vede solo l'esito.
        if ($request->query('state') === 'admin-consent') {
            return $this->adminConsentResult($request);
        }

        if (! Auth::check()) {
            return redirect('/admin/login');
        }

        $pending = $request->session()->pull('mail_oauth');

        if (! $pending || ! hash_equals((string) $pending['state'], (string) $request->query('state'))) {
            abort(403, 'Richiesta di collegamento non valida o scaduta: riparti dal pulsante «Collega account Microsoft».');
        }

        $account = MailAccount::findOrFail($pending['account_id']);
        abort_unless($request->user()->canAccessTenant($account->company), 403);
        $back = "/admin/{$account->company_id}/mail-accounts/{$account->id}/edit";

        if ($request->query('error')) {
            Notification::make()->danger()->title('Collegamento annullato da Microsoft')->body(mb_substr((string) $request->query('error_description'), 0, 250))->persistent()->send();

            return redirect($back);
        }

        try {
            $oauth->complete($account, (string) $request->query('code'));
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Collegamento non riuscito')->body($e->getMessage())->persistent()->send();

            return redirect($back);
        }

        Notification::make()->success()->title('Account Microsoft collegato')->body("{$account->email_address}: ora puoi premere «Prova connessione».")->send();

        return redirect($back);
    }

    private function adminConsentResult(Request $request): Response
    {
        $granted = strtolower((string) $request->query('admin_consent')) === 'true';
        $title = $granted ? 'Consenso concesso' : 'Consenso non concesso';
        $message = $granted
            ? 'L\'app UnicoPEC è stata approvata per la tua organizzazione. Puoi chiudere questa pagina: ora si può collegare la casella.'
            : 'Il consenso non è stato concesso'.($request->query('error_description') ? ': '.e(mb_substr((string) $request->query('error_description'), 0, 250)) : '.');

        return response("<!doctype html><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$title}</title>"
            .'<body style="font-family:Arial,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#1f2937">'
            ."<h1 style=\"font-size:1.4rem\">{$title}</h1><p>{$message}</p></body>");
    }
}
