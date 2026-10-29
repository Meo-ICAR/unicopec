<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Models\IncomingEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sceglie il referente di un'email dall'organigramma della società: chi ha il ruolo della categoria (`employees.employee_roles`),
 * altrimenti, risalendo la gerarchia (`employee_types.managed_by_code`), chi gestisce quel ruolo, poi i ruoli di ripiego della categoria.
 * Tra più titolari vince chi ha meno email aperte assegnate. Una società senza dipendenti usa l'organigramma di ripiego (config pec.org_fallback_company_id).
 */
class EmailRouter
{
    /** @return array{employee: Employee, role: string, via: string, org_company_id: int}|null null = nessun referente trovato o categoria non smistabile */
    public function route(IncomingEmail $email, EmailCategory $category): ?array
    {
        if (! $category->isRoutable()) {
            return null;
        }

        $orgId = $this->orgCompanyId($email->company_id);
        $staff = Employee::query()->where('company_id', $orgId)->where('is_active', true)->whereNotNull('email')->get();

        foreach ($this->rolesToTry($category) as [$role, $via]) {
            $holders = $staff->filter(fn (Employee $e) => in_array($role, $this->rolesOf($e), true));

            if ($holders->isNotEmpty()) {
                return ['employee' => $this->leastLoaded($holders), 'role' => $role, 'via' => $via, 'org_company_id' => $orgId];
            }
        }

        return null;
    }

    public function orgCompanyId(?int $companyId): int
    {
        $hasStaff = $companyId && Employee::query()->where('company_id', $companyId)->where('is_active', true)->exists();

        return $hasStaff ? $companyId : (int) config('pec.org_fallback_company_id');
    }

    /** @return array<int, array{0: string, 1: string}> [ruolo, come ci si arriva] */
    private function rolesToTry(EmailCategory $category): array
    {
        $tried = [];
        $add = function (string $role, string $via) use (&$tried) {
            if (! collect($tried)->contains(fn ($t) => $t[0] === $role)) {
                $tried[] = [$role, $via];
            }
        };

        $add($category->role(), 'ruolo competente');

        // Chi gestisce il ruolo (managed_by_code → nome del ruolo superiore), a catena.
        $role = $category->role();
        for ($i = 0; $i < 4 && $role; $i++) {
            $managerCode = DB::table('employee_types')->whereRaw('lower(name) = ?', [$role])->value('managed_by_code');
            $role = $managerCode ? DB::table('employee_types')->where('code', $managerCode)->value('name') : null;

            if ($role) {
                $add(mb_strtolower($role), "gerarchia: {$managerCode}");
            }
        }

        foreach ($category->fallbackRoles() as $fallback) {
            $add($fallback, 'ruolo di ripiego');
        }

        return $tried;
    }

    /** @return array<int, string> */
    private function rolesOf(Employee $employee): array
    {
        return collect($employee->employee_roles)->map(fn ($r) => mb_strtolower(trim((string) $r)))->all();
    }

    /** Tra i titolari, preferisce chi è interno e ha meno email aperte (non lette) già assegnate. */
    private function leastLoaded(Collection $holders): Employee
    {
        $open = IncomingEmail::query()->where('related_type', 'employee')->where('is_read', false)
            ->selectRaw('related_id, count(*) n')->groupBy('related_id')->pluck('n', 'related_id');

        return $holders->sortBy([
            fn (Employee $a, Employee $b) => (int) $a->is_external <=> (int) $b->is_external,
            fn (Employee $a, Employee $b) => ($open[$a->id] ?? 0) <=> ($open[$b->id] ?? 0),
            fn (Employee $a, Employee $b) => $a->id <=> $b->id,
        ])->first();
    }
}
