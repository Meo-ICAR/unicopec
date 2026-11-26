<?php

namespace Tests\Unit;

use App\Services\Mail\ProducerNameResolver;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ProducerNameResolverTest extends TestCase
{
    private function producers(): Collection
    {
        return new Collection([
            (object) ['id' => 103, 'name' => 'Manlio Albanese', 'email' => 'manlio.albanese@agenti.races.it'],
            (object) ['id' => 127, 'name' => 'ALBANESE Manlio', 'email' => 'manlio.albanese@agenti.races.it'],
            (object) ['id' => 129, 'name' => "GIOFRE' ALFONSO", 'email' => 'alfonso.giofre@agenti.races.it'],
            (object) ['id' => 130, 'name' => 'Rossi Mario', 'email' => 'mario.rossi@x.it'],
            (object) ['id' => 131, 'name' => 'Rossi Luca', 'email' => 'luca.rossi@x.it'],
        ])->map(function ($p) {
            return new class($p) {
                public function __construct(private object $p) {}

                public function __get($k)
                {
                    return $this->p->$k;
                }

                public function getKey()
                {
                    return $this->p->id;
                }
            };
        });
    }

    public function test_ignora_maiuscole_accenti_e_apostrofi(): void
    {
        $this->assertSame([129], ProducerNameResolver::match($this->producers(), 'Giofrè')->map->getKey()->all());
    }

    public function test_le_schede_doppie_con_lo_stesso_indirizzo_valgono_una_volta(): void
    {
        $this->assertSame([103], ProducerNameResolver::match($this->producers(), 'Albanese')->map->getKey()->all());
    }

    public function test_un_cognome_in_comune_tra_persone_diverse_da_piu_risultati(): void
    {
        $this->assertCount(2, ProducerNameResolver::match($this->producers(), 'Rossi'));
        $this->assertSame([130], ProducerNameResolver::match($this->producers(), 'Rossi Mario')->map->getKey()->all());
    }

    public function test_nome_inesistente_o_vuoto_non_trova_nessuno(): void
    {
        $this->assertCount(0, ProducerNameResolver::match($this->producers(), 'Verdi'));
        $this->assertCount(0, ProducerNameResolver::match($this->producers(), '  '));
    }
}
