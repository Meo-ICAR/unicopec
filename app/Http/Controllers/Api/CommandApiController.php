<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunBridgeCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Bridge per UnicoBPM: elenca i comandi consentiti, ne mette in coda uno (202 con l'id del lancio) e ne espone l'esito. */
class CommandApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['commands' => config('schedulable_commands')]);
    }

    public function store(Request $request, string $command): JsonResponse
    {
        $allowed = config("schedulable_commands.{$command}");

        if ($allowed === null) {
            return response()->json(['message' => "Comando '{$command}' non consentito."], 404);
        }

        $options = $request->input('options', []);

        if (! is_array($options)) {
            return response()->json(['message' => 'Il campo "options" deve essere un oggetto.'], 422);
        }

        $unknown = array_diff(array_keys($options), $allowed['options']);

        if ($unknown !== []) {
            return response()->json(['message' => 'Opzioni non consentite: '.implode(', ', $unknown).'.', 'allowed_options' => $allowed['options']], 422);
        }

        $runId = (string) Str::uuid();
        Cache::put(RunBridgeCommand::key($runId), ['id' => $runId, 'command' => $command, 'status' => 'queued', 'queued_at' => now()->toIso8601String()], now()->addDay());
        RunBridgeCommand::dispatch($runId, $command, $options);

        return response()->json(['message' => "Comando '{$command}' accodato per l'esecuzione.", 'command' => $command, 'run_id' => $runId], 202);
    }

    public function show(string $run): JsonResponse
    {
        $data = Cache::get(RunBridgeCommand::key($run));

        return $data ? response()->json($data) : response()->json(['message' => 'Lancio non trovato.'], 404);
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'app' => 'unicopec', 'time' => now()->toIso8601String()]);
    }
}
