<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index()->comment('Società a cui vale la regola; vuoto = tutte');
            $table->string('name');
            $table->unsignedInteger('priority')->default(100)->comment('Più basso vince');
            $table->boolean('is_active')->default(true);
            $table->string('category', 50)->comment('Valore di EmailCategory');
            $table->string('sender_pattern', 500)->nullable();
            $table->string('subject_pattern', 500)->nullable();
            $table->string('body_pattern', 500)->nullable();
            $table->string('operator', 3)->default('or')->comment('and | or tra le condizioni compilate');
            $table->timestamps();
        });

        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('classification_rule_id')->nullable()->comment('Regola che ha deciso la categoria');
        });

        $this->insertDefaults();
    }

    /** Le tre regole che prima stavano nel codice (EmailClassifier::RULES). */
    public function insertDefaults(): void
    {
        $privacy = '\bRPD\b|\bDPO\b|garante per la protezione dei dati';
        $now = now();

        DB::table('classification_rules')->insert([
            ['name' => 'Garante privacy', 'priority' => 10, 'category' => 'dpo_privacy', 'sender_pattern' => '@pec\.gpdp\.it$', 'subject_pattern' => $privacy, 'body_pattern' => $privacy, 'operator' => 'or'],
            ['name' => 'Agenzia delle Entrate e Finanze', 'priority' => 20, 'category' => 'fiscale', 'sender_pattern' => '(agenziariscossione|agenziaentrate|finanze)\.gov\.it$', 'subject_pattern' => null, 'body_pattern' => null, 'operator' => 'or'],
            ['name' => 'INPS e INAIL', 'priority' => 30, 'category' => 'previdenza_lavoro', 'sender_pattern' => '(postacert\.)?inps\.gov\.it$|inail\.it$', 'subject_pattern' => null, 'body_pattern' => null, 'operator' => 'or'],
        ]);

        DB::table('classification_rules')->whereNull('created_at')->update(['created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('incoming_emails', fn (Blueprint $table) => $table->dropColumn('classification_rule_id'));
        Schema::dropIfExists('classification_rules');
    }
};
