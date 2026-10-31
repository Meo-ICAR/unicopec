<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_blacklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index()->comment('Società a cui vale; vuoto = tutte');
            $table->string('pattern')->comment('Indirizzo (mario@x.it), dominio (x.it, vale anche per i sottodomini) o jolly (*@news.*)');
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('sender_blacklists')->insert(collect([
            'microsoft.com' => 'Microsoft: newsletter e novità dei prodotti',
            'linkedin.com' => 'LinkedIn: notifiche e inviti',
            'facebookmail.com' => 'Facebook: notifiche',
            'mail.instagram.com' => 'Instagram: notifiche',
            'youtube.com' => 'YouTube: notifiche',
        ])->map(fn ($note, $pattern) => ['company_id' => null, 'pattern' => $pattern, 'note' => $note, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_blacklists');
    }
};
