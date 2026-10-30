<?php

namespace App\Services\Protocol;

use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use Illuminate\Database\Eloquent\Collection;

/** Protocollo di presa in carico: la persona sceglie il registro tra quelli collegati alle funzioni che ricopre. */
class PresaInCaricoProtocoller
{
    public function __construct(private ProtocolNumberer $numberer) {}

    /** @return Collection<int, ProtocolRegister> registri attivi della società dell'email collegati alle funzioni della persona */
    public function registersFor(IncomingEmail $email, Employee $employee): Collection
    {
        return ProtocolRegister::forRoles((int) $email->company_id, array_values((array) $employee->employee_roles));
    }

    /** @return Collection<int, ProtocolRegister> tutti i registri attivi della società: li può scegliere un amministratore dal pannello */
    public function allFor(IncomingEmail $email): Collection
    {
        return ProtocolRegister::query()->where('company_id', $email->company_id)->where('is_active', true)->orderBy('code')->get();
    }

    /** La società ha almeno un registro attivo? Se no, il protocollo non è in uso e la presa in carico resta com'era. */
    public function isInUse(IncomingEmail $email): bool
    {
        return $email->company_id !== null && ProtocolRegister::query()->where('company_id', $email->company_id)->where('is_active', true)->exists();
    }

    public function protocol(IncomingEmail $email, Employee $employee, ProtocolRegister $register, ?int $userId): Protocol
    {
        $parent = $email->protocols()->where('type', Protocol::SMISTAMENTO)->first();
        $role = collect((array) $employee->employee_roles)->first(fn ($r) => in_array($r, $register->roleNames(), true));

        return $this->numberer->assign($register, $email, Protocol::PRESA_IN_CARICO, $role, $employee, $parent, $userId);
    }
}
