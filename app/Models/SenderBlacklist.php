<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** Mittente escluso dall'importo delle email (newsletter, social, notifiche): indirizzo, dominio (anche i sottodomini) o jolly. */
class SenderBlacklist extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Voci attive che valgono per la società (le sue e quelle di tutte). @return Collection<int, string> modelli già normalizzati */
    public static function patternsFor(?int $companyId): Collection
    {
        return static::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('company_id')->when($companyId, fn ($w) => $w->orWhere('company_id', $companyId)))
            ->pluck('pattern')->map(fn (string $p) => self::normalize($p))->filter()->unique()->values();
    }

    public static function normalize(string $pattern): string
    {
        return ltrim(mb_strtolower(trim($pattern)), '@');
    }

    /** @param Collection<int, string> $patterns da patternsFor() */
    public static function matches(Collection $patterns, ?string $address): bool
    {
        $address = mb_strtolower(trim((string) $address));

        if ($address === '' || $patterns->isEmpty()) {
            return false;
        }

        $domain = str_contains($address, '@') ? substr($address, strrpos($address, '@') + 1) : $address;

        return $patterns->contains(fn (string $p) => $address === $p
            || $domain === $p
            || str_ends_with($domain, '.'.$p)
            || (str_contains($p, '*') && fnmatch($p, $address)));
    }
}
