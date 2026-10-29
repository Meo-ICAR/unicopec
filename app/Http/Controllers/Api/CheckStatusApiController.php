<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bpm\PecChecks;
use Illuminate\Http\JsonResponse;

class CheckStatusApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['checks' => PecChecks::all()]);
    }

    public function show(string $command): JsonResponse
    {
        $status = PecChecks::run($command);

        if ($status === null) {
            return response()->json(['message' => "Check '{$command}' non disponibile."], 404);
        }

        return response()->json(['command' => $command] + $status);
    }
}
