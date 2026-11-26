<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sender_blacklists') && ! Schema::hasColumn('sender_blacklists', 'match_type')) {
            Schema::table('sender_blacklists', function (Blueprint $table) {
                $table->string('match_type', 10)->default('sender')->after('pattern')->comment('Su cosa si confronta: sender = mittente (indirizzo o dominio), subject = testo contenuto nell\'oggetto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sender_blacklists', 'match_type')) {
            Schema::table('sender_blacklists', fn (Blueprint $table) => $table->dropColumn('match_type'));
        }
    }
};
