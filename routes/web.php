<?php

use App\Http\Controllers\BpmBridgeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Accesso (SSO) da UnicoBPM. Throttling per limitare il brute force sul token.
Route::get('/bpm-landing/{subject_id}', [BpmBridgeController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('bpm.landing');
