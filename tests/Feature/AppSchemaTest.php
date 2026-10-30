<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class AppSchemaTest extends TestCase
{
    use UsesAppSchema;

    public function test_app_schema_can_be_built_on_sqlite(): void
    {
        $this->setUpAppSchema();

        $this->assertTrue(Schema::hasTable('email_events'));
        $this->assertTrue(Schema::hasTable('media'));
        $this->assertTrue(Schema::hasColumn('incoming_emails', 'taken_at'));
    }
}
