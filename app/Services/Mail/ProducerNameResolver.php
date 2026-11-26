<?php

namespace App\Services\Mail;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Unico\Core\Models\Fornitore;

/**
 * Abbina i nomi o cognomi citati in un'email (es. «Albanese», «Giofrè») ai produttori (`fornitori` del core) della società.
 * Il confronto ignora maiuscole, accenti e apostrofi («Giofrè» = «GIOFRE' ALFONSO»); con un solo cognome in comune tra più persone
 * NON si sceglie: il nome è segnalato come ambiguo, perché un audit o una comunicazione ufficiale non deve andare alla persona sbagliata.
 */
class ProducerNameResolver
{
    /**
     * @param  array<int, string>  $names  come scritti nell'email
     * @return array{ids: array<int, int|string>, found: array<int, array{id: int|string, nome: string, email: ?string}>, flags: array<int, string>}
     */
    public function resolve(array $names, ?int $companyId): array
    {
        $producers = Fornitore::query()->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'name', 'email']);

        $found = [];
        $flags = [];

        foreach ($names as $name) {
            $matches = self::match($producers, (string) $name);

            if ($matches->isEmpty()) {
                $flags[] = 'produttore_non_trovato: '.trim((string) $name);
            } elseif ($matches->count() > 1) {
                $flags[] = 'produttore_ambiguo: '.trim((string) $name).' ('.$matches->pluck('name')->map(fn ($n) => Str::title(mb_strtolower((string) $n)))->implode(', ').')';
            } else {
                $producer = $matches->first();
                $found[$producer->getKey()] = ['id' => $producer->getKey(), 'nome' => (string) $producer->name, 'email' => $producer->email];
            }
        }

        return ['ids' => array_keys($found), 'found' => array_values($found), 'flags' => $flags];
    }

    /**
     * Produttori il cui nome contiene tutte le parole cercate. Le schede doppie della stessa persona (stesso indirizzo email) valgono una sola volta.
     *
     * @param  Collection<int, Fornitore>  $producers
     * @return Collection<int, Fornitore>
     */
    public static function match(Collection $producers, string $query): Collection
    {
        $words = array_filter(explode(' ', self::normalize($query)));

        if ($words === []) {
            return new Collection;
        }

        return $producers
            ->filter(fn ($p) => array_diff($words, explode(' ', self::normalize((string) $p->name))) === [])
            ->sortBy(fn ($p) => $p->getKey())
            ->unique(fn ($p) => mb_strtolower(trim((string) $p->email)) ?: 'id:'.$p->getKey())
            ->values();
    }

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z]+/', ' ', mb_strtolower(Str::ascii($text))));
    }
}
