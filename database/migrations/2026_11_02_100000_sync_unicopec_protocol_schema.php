<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fa confluire in unicosuite le tabelle proprie di unicopec che mancano (regole di classificazione, protocolli, blacklist mittenti)
 * e le colonne di incoming_emails per regola, archiviazione e presa in carico. Idempotente: crea solo ciò che manca e si può rilanciare.
 * Non copia i dati: vanno trasferiti a parte. email_events e taken_by_id/taken_at/taken_via/awaiting_since sono in 2026_10_29_170000_sync_unicopec_schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('classification_rules')) {
            Schema::create('classification_rules', function (Blueprint $table) {
                $table->comment('Regole che assegnano una categoria alle email/PEC in arrivo in base a mittente, oggetto e corpo');

                $table->id()->comment('Identificativo della regola');
                $table->unsignedBigInteger('company_id')->nullable()->index()->comment('Società a cui vale la regola; vuoto = tutte');
                $table->string('name')->comment('Nome leggibile della regola');
                $table->unsignedInteger('priority')->default(100)->comment('Ordine di valutazione: più basso vince');
                $table->boolean('is_active')->default(true)->comment('Regola attiva (1) o disattivata (0)');
                $table->string('category', 50)->comment('Categoria assegnata, valore di EmailCategory');
                $table->string('sender_pattern', 500)->nullable()->comment('Espressione regolare sul mittente');
                $table->string('subject_pattern', 500)->nullable()->comment('Espressione regolare sull\'oggetto');
                $table->string('body_pattern', 500)->nullable()->comment('Espressione regolare sul corpo');
                $table->string('operator', 3)->default('or')->comment('and | or tra le condizioni compilate');
                $table->timestamp('created_at')->nullable()->comment('Data e ora di creazione');
                $table->timestamp('updated_at')->nullable()->comment('Data e ora di ultima modifica');
            });
        }

        if (! Schema::hasTable('protocol_registers')) {
            Schema::create('protocol_registers', function (Blueprint $table) {
                $table->comment('Registri di protocollo di una società: ognuno ha una sigla e numera i protocolli per anno');

                $table->id()->comment('Identificativo del registro');
                $table->unsignedBigInteger('company_id')->index()->comment('Società proprietaria del registro (companies.id)');
                $table->string('code', 10)->comment('Sigla nel numero di protocollo, es. FIS');
                $table->string('name')->comment('Nome leggibile del registro');
                $table->boolean('is_active')->default(true)->comment('Registro attivo (1) o disattivato (0)');
                $table->timestamp('created_at')->nullable()->comment('Data e ora di creazione');
                $table->timestamp('updated_at')->nullable()->comment('Data e ora di ultima modifica');

                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('protocol_register_counters')) {
            Schema::create('protocol_register_counters', function (Blueprint $table) {
                $table->comment('Ultimo numero di protocollo assegnato per ogni registro e anno');

                $table->unsignedBigInteger('protocol_register_id')->comment('Registro a cui appartiene il contatore (protocol_registers.id)');
                $table->unsignedSmallInteger('year')->comment('Anno di numerazione');
                $table->unsignedInteger('last_number')->default(0)->comment('Ultimo numero assegnato nell\'anno');

                $table->primary(['protocol_register_id', 'year']);
            });
        }

        if (! Schema::hasTable('protocol_register_roles')) {
            Schema::create('protocol_register_roles', function (Blueprint $table) {
                $table->comment('Funzioni aziendali abilitate a protocollare su ciascun registro');

                $table->unsignedBigInteger('protocol_register_id')->comment('Registro abilitato (protocol_registers.id)');
                $table->string('role')->comment('Nome della funzione aziendale (employee_types.name)');

                $table->primary(['protocol_register_id', 'role']);
            });
        }

        if (! Schema::hasTable('protocols')) {
            Schema::create('protocols', function (Blueprint $table) {
                $table->comment('Protocolli assegnati alle email/PEC: smistamento verso una funzione o presa in carico da un dipendente');

                $table->id()->comment('Identificativo del protocollo');
                $table->unsignedBigInteger('protocol_register_id')->index()->comment('Registro che ha numerato il protocollo (protocol_registers.id)');
                $table->unsignedSmallInteger('year')->comment('Anno di numerazione');
                $table->unsignedInteger('number')->comment('Numero progressivo nel registro e nell\'anno');
                $table->string('identifier', 40)->index()->comment('Numero di protocollo completo, es. FIS/2026/0001');
                $table->unsignedBigInteger('incoming_email_id')->index()->comment('Email o PEC protocollata (incoming_emails.id)');
                $table->string('type', 20)->comment('smistamento | presa_in_carico');
                $table->string('role')->nullable()->comment('Funzione aziendale destinataria dello smistamento');
                $table->unsignedBigInteger('employee_id')->nullable()->comment('Dipendente che ha preso in carico (employees.id)');
                $table->unsignedBigInteger('parent_protocol_id')->nullable()->comment('Protocollo di smistamento da cui deriva la presa in carico (protocols.id)');
                $table->unsignedBigInteger('created_by')->nullable()->comment('Utente che ha creato il protocollo (users.id)');
                $table->timestamp('protocolled_at')->comment('Data e ora di protocollazione');
                $table->timestamp('created_at')->nullable()->comment('Data e ora di creazione');
                $table->timestamp('updated_at')->nullable()->comment('Data e ora di ultima modifica');

                $table->unique(['protocol_register_id', 'year', 'number']);
                $table->unique(['incoming_email_id', 'type']);
            });
        }

        if (! Schema::hasTable('sender_blacklists')) {
            Schema::create('sender_blacklists', function (Blueprint $table) {
                $table->comment('Mittenti da ignorare: le email che corrispondono non vengono classificate né smistate');

                $table->id()->comment('Identificativo della voce di blacklist');
                $table->unsignedBigInteger('company_id')->nullable()->index()->comment('Società a cui vale; vuoto = tutte');
                $table->string('pattern')->comment('Indirizzo (mario@x.it), dominio (x.it, vale anche per i sottodomini) o jolly (*@news.*)');
                $table->string('note')->nullable()->comment('Motivo per cui il mittente è escluso');
                $table->boolean('is_active')->default(true)->comment('Voce attiva (1) o disattivata (0)');
                $table->timestamp('created_at')->nullable()->comment('Data e ora di creazione');
                $table->timestamp('updated_at')->nullable()->comment('Data e ora di ultima modifica');
            });
        }

        $this->addIncomingEmailColumns();
    }

    private function addIncomingEmailColumns(): void
    {
        if (! Schema::hasTable('incoming_emails')) {
            return;
        }

        $columns = [
            'classification_rule_id' => fn (Blueprint $table) => $table->unsignedBigInteger('classification_rule_id')->nullable()->comment('Regola di classificazione che ha deciso la categoria (classification_rules.id)'),
            'archive_status' => fn (Blueprint $table) => $table->string('archive_status', 20)->nullable()->index()->comment('Stato di archiviazione: da_archiviare | archiviata | errore'),
            'archive_reference' => fn (Blueprint $table) => $table->string('archive_reference')->nullable()->comment('Identificativo dato dal servizio di archiviazione'),
            'archived_at' => fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->comment('Data e ora dell\'ultimo invio in archivio'),
            'archived_protocols' => fn (Blueprint $table) => $table->unsignedTinyInteger('archived_protocols')->default(0)->comment('Quanti protocolli conteneva l\'ultimo invio in archivio'),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('incoming_emails', $name)) {
                Schema::table('incoming_emails', $definition);
            }
        }
    }

    public function down(): void
    {
        // Volutamente vuoto: su un database condiviso non si eliminano dati.
    }
};
