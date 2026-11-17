<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

/**
 * Il database è condiviso con il resto della suite (unicosuite): le migration devono potersi rilanciare su uno schema già completo
 * senza errori, senza cambiare lo schema e senza duplicare i dati inseriti (regole di classificazione predefinite).
 */
class MigrationsIdempotentTest extends TestCase
{
    use UsesAppSchema;

    public function test_rilanciare_tutte_le_migration_non_cambia_nulla(): void
    {
        $this->setUpAppSchema();

        $before = $this->snapshot();

        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $migration = require $file;
            $this->assertInstanceOf(Migration::class, $migration);
            $migration->up();
        }

        $this->assertSame($before, $this->snapshot());
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $tables = collect(Schema::getTables())->pluck('name')->sort()->values();

        return [
            'schema' => $tables->mapWithKeys(fn (string $table) => [$table => collect(Schema::getColumnListing($table))->sort()->values()->all()])->all(),
            'regole' => DB::table('classification_rules')->count(),
        ];
    }
}
