<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email di assistenza di UnicoPEC per società: a questo indirizzo l'Assistente AI inoltra le richieste di escalation.
 * La tabella companies è condivisa con il resto della suite (database unicosuite): idempotente, aggiunge la colonna solo se manca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'unicopec_support_email')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('unicopec_support_email')->nullable()->after('dpo_email')
                    ->comment('Email di assistenza a cui l\'Assistente AI di UnicoPEC inoltra le escalation; vuoto = indirizzo predefinito dell\'installazione');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'unicopec_support_email')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('unicopec_support_email');
            });
        }
    }
};
