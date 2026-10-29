<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ModelFieldValueApiController extends Controller
{
    /** Legge i campi (gli stessi di /fields, senza segreti) di un record, dato modello e id. */
    public function show(string $model, string $id, ModelFieldIntrospector $introspector): JsonResponse
    {
        try {
            $modelClass = $introspector->resolve($model);
            $columns = collect($introspector->columns($model))->pluck('name');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $record = $modelClass::find($id);

        if (! $record) {
            return response()->json(['message' => 'Record non trovato.'], 404);
        }

        return response()->json(['model' => $model, 'id' => $id, 'fields' => $record->only($columns->all())]);
    }

    /** Scrive un campo di un record, solo tra quelli consentiti a UnicoBPM (vedi ModelFieldIntrospector::WRITABLE). */
    public function update(Request $request, string $model, string $id, ModelFieldIntrospector $introspector): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        try {
            $modelClass = $introspector->resolve($model);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        if (! in_array($validated['field'], $introspector->writable($model), true)) {
            return response()->json(['message' => "Il campo '{$validated['field']}' non è scrivibile su {$model}."], 422);
        }

        $record = $modelClass::find($id);

        if (! $record) {
            return response()->json(['message' => 'Record non trovato.'], 404);
        }

        $record->update([$validated['field'] => $validated['value']]);

        return response()->json(['field' => $validated['field'], 'value_stored' => $record->getAttribute($validated['field'])]);
    }
}
