<?php

use App\Http\Controllers\Api\CheckStatusApiController;
use App\Http\Controllers\Api\CommandApiController;
use App\Http\Controllers\Api\IncomingEmailApiController;
use App\Http\Controllers\Api\LatestModelRecordApiController;
use App\Http\Controllers\Api\ModelFieldsApiController;
use App\Http\Controllers\Api\ModelFieldValueApiController;
use App\Http\Controllers\Api\UserLookupApiController;
use App\Http\Middleware\VerifyBridgeKey;
use Illuminate\Support\Facades\Route;

// Consumato da UnicoBPM: la chiave condivisa X-Api-Key (BPM_BRIDGE_API_KEY) è obbligatoria, senza chiave l'API risponde 503.
Route::middleware([VerifyBridgeKey::class, 'throttle:300,1'])->group(function () {
    // Colonne, ultimo record, lettura e scrittura (solo campi consentiti) dei modelli: per configurare i processi e scrivere l'esito.
    Route::get('/models/{model}/fields', [ModelFieldsApiController::class, 'show'])->name('api.models.fields');
    Route::get('/models/{model}/latest', [LatestModelRecordApiController::class, 'show'])->name('api.models.latest');
    Route::get('/models/{model}/{id}', [ModelFieldValueApiController::class, 'show'])->name('api.models.show-record');
    Route::patch('/models/{model}/{id}', [ModelFieldValueApiController::class, 'update'])->name('api.models.update-field');

    // Dettaglio di un'email ricevuta (il `subject_ref` dell'evento `email.ricevuta`).
    Route::get('/incoming-emails/{id}', [IncomingEmailApiController::class, 'show'])->name('api.incoming-emails.show');

    // Per lo switcher «cambia app» di UnicoBPM: l'utente ha un account con accesso al pannello?
    Route::get('/users/lookup', [UserLookupApiController::class, 'show'])->name('api.users.lookup');

    // Stato dei controlli (valore + severity): chi avvisare lo decide UnicoBPM con la RACI.
    Route::get('/checks', [CheckStatusApiController::class, 'index'])->name('api.checks.index');
    Route::get('/checks/{command}', [CheckStatusApiController::class, 'show'])->name('api.checks.show');

    // Bridge dei comandi in whitelist (config/schedulable_commands.php).
    Route::get('/commands', [CommandApiController::class, 'index'])->name('api.commands.index');
    Route::get('/commands/runs/{run}', [CommandApiController::class, 'show'])->name('api.commands.runs.show');
    Route::post('/commands/{command}', [CommandApiController::class, 'store'])->name('api.commands.store');
    Route::get('/bridge/health', [CommandApiController::class, 'health'])->name('api.bridge.health');
});
