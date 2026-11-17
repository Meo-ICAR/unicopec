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
            if (! Schema::hasColumn('incoming_emails', 'archive_status')) {
                $table->string('archive_status', 20)->nullable()->index()->comment('da_archiviare | archiviata | errore');
            }

            if (! Schema::hasColumn('incoming_emails', 'archive_reference')) {
                $table->string('archive_reference')->nullable()->comment('Identificativo dato dal servizio di archiviazione');
            }

            if (! Schema::hasColumn('incoming_emails', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }

            if (! Schema::hasColumn('incoming_emails', 'archived_protocols')) {
                $table->unsignedTinyInteger('archived_protocols')->default(0)->comment('Quanti protocolli conteneva l\'ultimo invio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incoming_emails', fn (Blueprint $table) => $table->dropColumn(['archive_status', 'archive_reference', 'archived_at', 'archived_protocols']));
    }
};
