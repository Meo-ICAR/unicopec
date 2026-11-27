<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Il mittente fisso degli inoltri viene dal .env (casella vera): i test non lo usano.
        config(['pec.forward.from_account_id' => null]);

        // I test non devono mai toccare il database vero: con la cache di configurazione attiva si userebbe quello di produzione.
        foreach (array_unique([config('database.default'), config('unico-core.connection')]) as $connection) {
            $database = (string) config("database.connections.{$connection}.database");

            if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
                throw new RuntimeException("Test interrotto: la connessione \"{$connection}\" punta a \"{$database}\", che non è un database di prova.");
            }
        }
    }
}
