<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** Email escluse dall'importo (newsletter, social, notifiche): per mittente (indirizzo, dominio con i sottodomini, jolly) o per testo contenuto nell'oggetto. */
class SenderBlacklist extends Model
{
    public const SENDER = 'sender';

    public const SUBJECT = 'subject';

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
    public static function patternsFor(?int $companyId, string $type = self::SENDER): Collection
    {
        return static::query()->where('is_active', true)
            ->where(fn ($q) => $type === self::SENDER ? $q->whereNull('match_type')->orWhere('match_type', self::SENDER) : $q->where('match_type', $type))
            ->where(fn ($q) => $q->whereNull('company_id')->when($companyId, fn ($w) => $w->orWhere('company_id', $companyId)))
            ->pluck('pattern')->map(fn (string $p) => $type === self::SUBJECT ? self::normalizeSubject($p) : self::normalize($p))->filter()->unique()->values();
    }

    public static function normalize(string $pattern): string
    {
        return ltrim(mb_strtolower(trim($pattern)), '@');
    }

    public static function normalizeSubject(string $pattern): string
    {
        return mb_strtolower(trim($pattern));
    }

    /** L'oggetto contiene il testo (senza distinguere maiuscole e minuscole); con * il testo è un modello jolly. @param Collection<int, string> $patterns da patternsFor($company, SUBJECT) */
    public static function matchesSubject(Collection $patterns, ?string $subject): bool
    {
        $subject = mb_strtolower(trim((string) $subject));

        if ($subject === '' || $patterns->isEmpty()) {
            return false;
        }

        return $patterns->contains(fn (string $p) => str_contains($p, '*') ? fnmatch($p, $subject) : str_contains($subject, $p));
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
