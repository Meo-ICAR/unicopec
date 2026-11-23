<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Unico\Core\Models\MailAccount;

/**
 * Casella recruiting@races.it tra quelle che unicopec legge (`mail_accounts`): presidio condiviso del processo di reclutamento della rete
 * (unicobpm, PRC-RECLUTAMENTO-RETE), che la riceve sempre in CC. Parte con le impostazioni della casella proforma@races.it (stesso
 * Microsoft 365, accesso OAuth2) ma **disattiva e senza credenziali**: va autorizzata dall'app (accesso Microsoft) e poi attivata.
 * Idempotente: se la casella esiste non si tocca.
 */
class RecruitingMailAccountSeeder extends Seeder
{
    public const ADDRESS = 'recruiting@races.it';

    public function run(): void
    {
        if (MailAccount::query()->where('email_address', self::ADDRESS)->exists()) {
            $this->command?->info(self::ADDRESS.' esiste già.');

            return;
        }

        $base = MailAccount::query()->where('email_address', 'proforma@races.it')->first();

        $account = new MailAccount;
        $account->forceFill([
            'name' => 'Recruiting Races (presidio reclutamento rete)',
            'email_address' => self::ADDRESS,
            'protocol' => $base?->protocol ?? 'imap',
            'type' => 'generale',
            'is_pec' => false,
            'company_id' => $base?->company_id,
            'imap_host' => $base?->imap_host ?? 'outlook.office365.com',
            'imap_port' => $base?->imap_port ?? 993,
            'imap_encryption' => $base?->imap_encryption ?? 'ssl',
            'imap_username' => self::ADDRESS,
            'smtp_host' => $base?->smtp_host ?? 'smtp.office365.com',
            'smtp_port' => $base?->smtp_port ?? 587,
            'smtp_encryption' => $base?->smtp_encryption ?? 'tls',
            'smtp_username' => self::ADDRESS,
            'auth_type' => $base?->auth_type ?? 'oauth2',
            'provider' => $base?->provider ?? 'microsoft',
            // Senza credenziali non si può leggere: resta disattiva finché non si autorizza l'accesso.
            'is_active' => false,
        ])->save();

        $this->command?->info(self::ADDRESS.' creata (disattiva, da autorizzare).');
    }
}
