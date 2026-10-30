<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_registers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 10)->comment('Sigla nel numero, es. FIS');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('protocol_register_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('protocol_register_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['protocol_register_id', 'year']);
        });

        Schema::create('protocol_register_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('protocol_register_id');
            $table->string('role')->comment('Nome della funzione aziendale (employee_types.name)');
            $table->primary(['protocol_register_id', 'role']);
        });

        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('protocol_register_id')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->string('identifier', 40)->index();
            $table->unsignedBigInteger('incoming_email_id')->index();
            $table->string('type', 20)->comment('smistamento | presa_in_carico');
            $table->string('role')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('parent_protocol_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('protocolled_at');
            $table->timestamps();
            $table->unique(['protocol_register_id', 'year', 'number']);
            $table->unique(['incoming_email_id', 'type']);
        });
    }

    public function down(): void
    {
        foreach (['protocols', 'protocol_register_roles', 'protocol_register_counters', 'protocol_registers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
