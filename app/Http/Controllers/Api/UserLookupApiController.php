<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLookupApiController extends Controller
{
    /** Consumato da UnicoBPM: dice se l'utente ha un account con accesso al pannello, per proporgli «cambia app». */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $validated['email'])->first();

        return response()->json(['exists' => (bool) $user && $user->canAccessPanel(filament()->getPanel('admin'))]);
    }
}
