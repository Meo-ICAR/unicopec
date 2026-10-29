<?php

use App\Http\Controllers\BpmBridgeController;
use App\Http\Controllers\MicrosoftMailOAuthController;
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
