<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Services\Mail\MicrosoftMailOAuth;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
    public function callback(Request $request, MicrosoftMailOAuth $oauth): RedirectResponse
    {
        $pending = $request->session()->pull('mail_oauth');

        if (! $pending || ! hash_equals((string) $pending['state'], (string) $request->query('state'))) {
            abort(403, 'Richiesta di collegamento non valida o scaduta: riparti dal pulsante «Collega account Microsoft».');
        }

        $account = MailAccount::findOrFail($pending['account_id']);
        $back = "/admin/mail-accounts/{$account->id}/edit";

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
}
