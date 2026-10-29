<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class LatestModelRecordApiController extends Controller
{
    /** L'ultimo record creato di un modello: UnicoBPM lo usa per accorgersi che una sincronizzazione si è fermata. */
    public function show(string $model, ModelFieldIntrospector $introspector): JsonResponse
    {
        try {
            $modelClass = $introspector->resolve($model);
            $columns = collect($introspector->columns($model))->pluck('name');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $record = $modelClass::query()->latest('created_at')->latest((new $modelClass)->getKeyName())->first();

        if (! $record) {
            return response()->json(['message' => 'Nessun record trovato.'], 404);
        }

        return response()->json(['model' => $model, 'id' => $record->getKey(), 'fields' => $record->only($columns->all())]);
    }
}
