<?php

namespace App\Services\Protocol;

use App\Enums\EmailCategory;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use Illuminate\Database\QueryException;

/**
 * Protocollo di smistamento: automatico appena l'email ha un referente, nel primo registro attivo (per sigla) della funzione competente
 * per la categoria. È unico per email: ripetere lo smistamento restituisce il protocollo già dato.
 */
class SmistamentoProtocoller
{
    public function __construct(private ProtocolNumberer $numberer) {}

    public function protocol(IncomingEmail $email): ?Protocol
    {
        $existing = $this->existing($email);

        if ($existing) {
            return $existing;
        }

        $role = EmailCategory::tryFrom((string) $email->classification)?->role();

        if ($role === null || $email->company_id === null) {
            return null;
        }

        $register = ProtocolRegister::forRoles((int) $email->company_id, [$role])->first();

        if (! $register) {
            $message = "Nessun registro di protocollo per la funzione «{$role}»";

            if (! $email->events()->where('kind', EmailEvent::ANOMALY)->where('message', $message)->exists()) {
                EmailEvent::record($email, EmailEvent::ANOMALY, 'error', $message, ['role' => $role]);
            }

            return null;
        }

        try {
            $protocol = $this->numberer->assign($register, $email, Protocol::SMISTAMENTO, $role, null);
        } catch (QueryException $e) {
            // Un'altra esecuzione ha protocollato nel frattempo: vale quel numero.
            return $this->existing($email) ?? throw $e;
        }

        $email->archive_status ??= 'da_archiviare';
        $email->save();

        EmailEvent::record($email, EmailEvent::PROTOCOLLED, 'ok', "Protocollata {$protocol->identifier} (smistamento)", ['protocol_id' => $protocol->id, 'identifier' => $protocol->identifier]);

        return $protocol;
    }

    private function existing(IncomingEmail $email): ?Protocol
    {
        return $email->protocols()->where('type', Protocol::SMISTAMENTO)->first();
    }
}
