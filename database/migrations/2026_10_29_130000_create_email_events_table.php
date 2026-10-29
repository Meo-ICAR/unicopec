<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro di ciò che succede a ogni email: classificazione, assegnazione, eventi verso UnicoBPM, inoltri, anomalie.
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incoming_email_id')->index();
            $table->string('kind', 30);     // classified | assigned | bpm_event | forwarded | anomaly
            $table->string('status', 20);   // ok | warning | error | resolved
            $table->string('message', 500);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
