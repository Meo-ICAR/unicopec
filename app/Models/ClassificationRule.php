<?php

namespace App\Models;

use App\Enums\EmailCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Regola di classificazione: se mittente, oggetto o corpo corrispondono ai pattern, l'email prende la categoria della regola. */
class ClassificationRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'category' => EmailCategory::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Regole attive che valgono per la società (le sue e le globali), nell'ordine di valutazione: priorità, poi quelle di società prima delle globali. */
    public static function matchesFor(?int $companyId): Builder
    {
        return static::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('company_id')->when($companyId, fn ($w) => $w->orWhere('company_id', $companyId)))
            ->orderBy('priority')->orderByRaw('company_id is null')->orderBy('id');
    }
}
