<?php

namespace App\Services\Assistant;

use App\Models\Company;
use App\Models\IncomingEmail;
use App\Models\MailAccount;

/** Riepilogo (non il testo) delle PEC di una società, dato in contesto all'assistente AI (ManualAssistantAgent). */
class PecAssistant
{
    public function summary(?Company $company): string
    {
        if (! $company) {
            return 'Nessuna società selezionata.';
        }

        $emails = IncomingEmail::query()->where('company_id', $company->id);
        $byCategory = (clone $emails)->selectRaw("coalesce(classification, 'da classificare') c, count(*) n")->groupBy('c')->pluck('n', 'c');
        $accounts = MailAccount::query()->where('company_id', $company->id)->get();

        return implode("\n", [
            "Società: {$company->name}",
            'Email ricevute: '.(clone $emails)->count().' (ultimi 7 giorni: '.(clone $emails)->where('received_at', '>=', now()->subDays(7))->count().')',
            'Per classificazione: '.($byCategory->map(fn ($n, $c) => "{$c}={$n}")->implode(', ') ?: 'nessuna'),
            'Senza referente: '.(clone $emails)->whereNull('related_id')->count(),
            'Caselle: '.($accounts->map(fn ($a) => $a->email_address.($a->is_pec ? ' (PEC)' : '').($a->is_active ? '' : ' [disattiva]').' ultima lettura '.($a->last_synced_at?->format('d/m/Y H:i') ?? 'mai'))->implode('; ') ?: 'nessuna'),
        ]);
    }
}
