<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Società a cui un utente può accedere (multicompany): stesso schema di unicogdpr.
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index('user_id');
        });

        // Ogni utente mantiene l'accesso alla società a cui era già collegato (users.company_id).
        DB::table('users')->whereNotNull('company_id')->whereNull('deleted_at')->orderBy('id')->each(function ($user) {
            DB::table('company_user')->insertOrIgnore([
                'company_id' => $user->company_id, 'user_id' => $user->id, 'role' => $user->role, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
