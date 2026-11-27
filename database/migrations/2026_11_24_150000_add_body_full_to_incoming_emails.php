<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // body_text contiene solo l'ultimo messaggio; il testo completo con la catena di risposte resta qui.
        if (! Schema::hasTable('incoming_emails') || Schema::hasColumn('incoming_emails', 'body_full')) {
            return;
        }

        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->longText('body_full')->nullable()->after('body_text');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('incoming_emails', 'body_full')) {
            return;
        }

        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->dropColumn('body_full');
        });
    }
};
