<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_emails', function (Blueprint $table) {
            // Presa in carico: chi (dipendente/consulente), quando e come. `awaiting_since` = inoltro manuale ancora senza presa in carico.
            $table->unsignedBigInteger('taken_by_id')->nullable()->index();
            $table->timestamp('taken_at')->nullable();
            $table->string('taken_via', 20)->nullable();
            $table->timestamp('awaiting_since')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->dropColumn(['taken_by_id', 'taken_at', 'taken_via', 'awaiting_since']);
        });
    }
};
