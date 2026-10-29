<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allinea un database di unicosuite allo schema di unicopec. Idempotente: crea solo ciò che manca e si può rilanciare.
 * Tabella: email_events. Colonne: incoming_emails.taken_by_id/taken_at/taken_via/awaiting_since.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_events')) {
            Schema::create('email_events', function (Blueprint $table) {
                $table->comment('Registro di ciò che succede a ogni email/PEC: classificazione, assegnazione, eventi verso UnicoBPM, inoltri, presa in carico, anomalie');

                $table->id()->comment('Identificativo della voce di registro');
                $table->unsignedBigInteger('incoming_email_id')->index()->comment('Email o PEC a cui si riferisce (incoming_emails.id)');
                $table->string('kind', 30)->comment('Tipo di voce: classified | assigned | bpm_event | forwarded | taken | anomaly');
                $table->string('status', 20)->comment('Esito: ok | warning | error | resolved');
                $table->string('message', 500)->comment('Descrizione leggibile di cosa è successo');
                $table->json('meta')->nullable()->comment('Dettagli strutturati della voce (categoria, urgenza, destinatari, referente, ecc.)');
                $table->timestamps();

                $table->index(['kind', 'status']);
            });
        }

        if (! Schema::hasColumn('incoming_emails', 'taken_by_id')) {
            Schema::table('incoming_emails', fn (Blueprint $table) => $table->unsignedBigInteger('taken_by_id')->nullable()->index()->comment('Dipendente o consulente (employees.id) che ha preso in carico l\'email'));
        }

        if (! Schema::hasColumn('incoming_emails', 'taken_at')) {
            Schema::table('incoming_emails', fn (Blueprint $table) => $table->timestamp('taken_at')->nullable()->comment('Data e ora della presa in carico'));
        }

        if (! Schema::hasColumn('incoming_emails', 'taken_via')) {
            Schema::table('incoming_emails', fn (Blueprint $table) => $table->string('taken_via', 20)->nullable()->comment('Come è stata presa in carico: link (pulsante nell\'email inoltrata) | manuale'));
        }

        if (! Schema::hasColumn('incoming_emails', 'awaiting_since')) {
            Schema::table('incoming_emails', fn (Blueprint $table) => $table->timestamp('awaiting_since')->nullable()->index()->comment('Da quando l\'email è stata inoltrata a mano e attende la presa in carico (vuoto se già presa in carico)'));
        }
    }

    public function down(): void
    {
        // Volutamente vuoto: su un database condiviso non si eliminano dati.
    }
};
