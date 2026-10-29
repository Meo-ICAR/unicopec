<?php

use App\Http\Controllers\BpmBridgeController;
use App\Http\Controllers\MicrosoftMailOAuthController;
use App\Http\Controllers\TakeEmailController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Accesso (SSO) da UnicoBPM. Throttling per limitare il brute force sul token.
Route::get('/bpm-landing/{subject_id}', [BpmBridgeController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('bpm.landing');

// Collegamento di una casella Microsoft 365 con OAuth 2.0 (solo utenti del pannello). L'indirizzo di ritorno va registrato nell'app su Azure.
// Il ritorno è fuori dal gruppo `auth` perché serve anche al consenso dell'amministratore del tenant (che non ha accesso qui); il controller richiede l'accesso negli altri casi.
Route::get('/mail-oauth/microsoft/callback', [MicrosoftMailOAuthController::class, 'callback'])->name('mail-oauth.microsoft.callback');
Route::middleware('auth')->group(function () {
    Route::get('/mail-oauth/microsoft/{account}', [MicrosoftMailOAuthController::class, 'redirect'])->whereNumber('account')->name('mail-oauth.microsoft.redirect');
});

// Presa in carico dal link firmato nella PEC inoltrata (il GET mostra la conferma, il POST la registra).
Route::middleware(['signed', 'throttle:30,1'])->prefix('pec/presa-in-carico')->group(function () {
    Route::get('/{email}/{employee}', [TakeEmailController::class, 'show'])->whereNumber(['email', 'employee'])->name('pec.take');
    Route::post('/{email}/{employee}', [TakeEmailController::class, 'store'])->whereNumber(['email', 'employee']);
});

// Manuali (HTML statici in resources/manuals), solo per chi ha fatto l'accesso.
Route::middleware('auth')->prefix('manuali')->group(function () {
    Route::get('/utente', fn () => response(file_get_contents(resource_path('manuals/manuale-utente.html'))))->name('manuali.utente');
    Route::get('/tecnico', fn () => response(file_get_contents(resource_path('manuals/manuale-tecnico.html'))))->name('manuali.tecnico');
});
