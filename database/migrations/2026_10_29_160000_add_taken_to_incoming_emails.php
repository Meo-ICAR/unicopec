<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: la tabella è condivisa con la suite (unicosuite), si aggiunge solo ciò che manca.
        Schema::table('incoming_emails', function (Blueprint $table) {
            // Presa in carico: chi (dipendente/consulente), quando e come. `awaiting_since` = inoltro manuale ancora senza presa in carico.
            if (! Schema::hasColumn('incoming_emails', 'taken_by_id')) {
                $table->unsignedBigInteger('taken_by_id')->nullable()->index();
            }

            if (! Schema::hasColumn('incoming_emails', 'taken_at')) {
                $table->timestamp('taken_at')->nullable();
            }

            if (! Schema::hasColumn('incoming_emails', 'taken_via')) {
                $table->string('taken_via', 20)->nullable();
            }

            if (! Schema::hasColumn('incoming_emails', 'awaiting_since')) {
                $table->timestamp('awaiting_since')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->dropColumn(['taken_by_id', 'taken_at', 'taken_via', 'awaiting_since']);
        });
    }
};
