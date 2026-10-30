<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le tabelle di base della suite (società, dipendenti, caselle, email) non sono nelle migrazioni di UnicoPEC ma nel database
 * ripristinato: per i test si creano qui con le sole colonne che l'app usa, poi si eseguono le migrazioni dell'app.
 */
trait UsesAppSchema
{
    protected function setUpAppSchema(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('vat_number', 50)->nullable();
            $t->string('pec')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('employee_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 50)->nullable();
            $t->string('managed_by_code', 50)->nullable();
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_external')->default(false);
            $t->json('employee_roles')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('mail_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('name')->nullable();
            $t->string('email_address')->nullable();
            $t->boolean('is_pec')->default(false);
            $t->boolean('is_active')->default(true);
            $t->string('type', 30)->default('generale');
            $t->string('auth_type')->default('password');
            $t->string('smtp_host')->nullable();
            $t->integer('smtp_port')->nullable();
            $t->string('smtp_username')->nullable();
            $t->text('smtp_password')->nullable();
            $t->string('smtp_encryption')->default('ssl');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('incoming_emails', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('mail_account_id')->nullable();
            $t->string('message_id')->nullable();
            $t->string('from_email')->nullable();
            $t->string('from_name')->nullable();
            $t->json('to')->nullable();
            $t->json('cc')->nullable();
            $t->string('subject')->nullable();
            $t->longText('body_text')->nullable();
            $t->longText('body_html')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->boolean('is_read')->default(false);
            $t->string('classification', 50)->nullable();
            $t->string('related_type')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('severity')->nullable();
            $t->string('subject')->nullable();
            $t->longText('body')->nullable();
            $t->json('placeholders')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('app_identifier', 50)->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('type', 30)->nullable();
            $t->timestamps();
        });

        // I modelli di unico-core leggono dalla connessione core (unicosuite): nei test è la stessa sqlite.
        config(['unico-core.connection' => config('database.default')]);

        $this->artisan('migrate', ['--force' => true]);

        // Colonne di users che l'app usa e che vengono dal database ripristinato.
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('user');
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('last_company_id')->nullable();
            $t->softDeletes();
        });
    }
}
