<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ModelFieldsApiController extends Controller
{
    /** Colonne di un modello, così UnicoBPM può offrire la select dei campi nella configurazione di un processo. */
    public function show(string $model, ModelFieldIntrospector $introspector): JsonResponse
    {
        try {
            return response()->json(['model' => $model, 'columns' => $introspector->columns($model)]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
