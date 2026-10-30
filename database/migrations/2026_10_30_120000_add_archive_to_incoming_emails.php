<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->string('archive_status', 20)->nullable()->index()->comment('da_archiviare | archiviata | errore');
            $table->string('archive_reference')->nullable()->comment('Identificativo dato dal servizio di archiviazione');
            $table->timestamp('archived_at')->nullable();
            $table->unsignedTinyInteger('archived_protocols')->default(0)->comment('Quanti protocolli conteneva l\'ultimo invio');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_emails', fn (Blueprint $table) => $table->dropColumn(['archive_status', 'archive_reference', 'archived_at', 'archived_protocols']));
    }
};
