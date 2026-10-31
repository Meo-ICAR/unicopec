<?php

namespace Database\Seeders;

use App\Models\ClassificationRule;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ProtocolRegister;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dotazione di Races Finance (società di tipo FINANCE, mediazione creditizia): funzioni aziendali mancanti, registri di protocollo
 * collegati alle funzioni e regole di classificazione del settore. I dipendenti e le funzioni già esistenti non si toccano.
 * Si può rilanciare: non duplica nulla. Va bene anche in produzione; la società dimostrativa è in FinanceDemoCompanySeeder.
 *
 *   php artisan db:seed --class=FinanceCompanySeeder
 */
class FinanceCompanySeeder extends Seeder
{
    /** Funzioni aziendali (employee_types): code, name, gestita da. */
    private const ROLES = [
        ['CDA', 'cda', null],
        ['INT-AUDIT', 'audit', 'CDA'],
        ['COMPLIANCE', 'compliance', 'CDA'],
        ['ANTIRICICLAGGIO', 'antiriciclaggio', 'CDA'],
        ['DPO', 'dpo', 'CDA'],
        ['LEGALE', 'legale', 'CDA'],
        ['IT', 'it', 'CDA'],
        ['ORG-RU', 'organizzazione e risorse umane', 'CDA'],
        ['SEGRETERIA', 'segreteria amministrativa', 'ORG-RU'],
        ['CONTABILE', 'contabile responsabile', 'CDA'],
        ['ISTRUTTORE', 'istruttore', 'CDA'],
        ['COMMERCIALE', 'commerciale', 'CDA'],
    ];

    /** Organigramma: nome, email, ruoli. */
    private const STAFF = [
        ['Rossi Marco', 'marco.rossi@finance-demo.it', ['cda']],
        ['Bianchi Laura', 'laura.bianchi@finance-demo.it', ['cda', 'affari societari e contabilità']],
        ['Verdi Paolo', 'paolo.verdi@finance-demo.it', ['compliance', 'antiriciclaggio']],
        ['Neri Giulia', 'giulia.neri@finance-demo.it', ['audit']],
        ['Gallo Stefano', 'stefano.gallo@finance-demo.it', ['dpo']],
        ['Ferri Elena', 'elena.ferri@finance-demo.it', ['legale']],
        ['Costa Andrea', 'andrea.costa@finance-demo.it', ['it']],
        ['Greco Chiara', 'chiara.greco@finance-demo.it', ['organizzazione e risorse umane']],
        ['Fontana Sara', 'segreteria@finance-demo.it', ['segreteria amministrativa', 'segreteria di rete']],
        ['Moretti Luca', 'luca.moretti@finance-demo.it', ['contabile responsabile']],
        ['Conti Marta', 'marta.conti@finance-demo.it', ['dipendente', 'istruttore']],
        ['Barbieri Davide', 'davide.barbieri@finance-demo.it', ['dipendente', 'istruttore']],
        ['Lombardi Silvia', 'silvia.lombardi@finance-demo.it', ['dipendente', 'commerciale']],
    ];

    /** Registri di protocollo: sigla, nome, funzioni collegate. */
    private const REGISTERS = [
        ['FIS', 'Fiscale e contabilità', ['contabile responsabile']],
        ['LEG', 'Legale e contenzioso', ['legale']],
        ['VIG', 'Vigilanza e compliance', ['compliance', 'audit']],
        ['AML', 'Antiriciclaggio', ['antiriciclaggio']],
        ['PRV', 'Privacy', ['dpo']],
        ['PRA', 'Pratiche clienti', ['istruttore']],
        ['RU', 'Risorse umane e previdenza', ['organizzazione e risorse umane']],
        ['GEN', 'Generale', ['cda', 'segreteria amministrativa']],
    ];

    /** Regole di classificazione del settore: nome, priorità, categoria, mittente, oggetto. */
    private const RULES = [
        ['OAM', 20, 'vigilanza', '@(pec\.)?organismo-am\.it$|oam\.it$', null],
        ['Banca d\'Italia', 21, 'vigilanza', '(bancaditalia|pec\.bancaditalia)\.it$', null],
        ['UIF e antiriciclaggio', 22, 'antiriciclaggio', '(uif\.)?bancaditalia\.it$', '\bUIF\b|operazione sospetta|adeguata verifica|antiriciclaggio'],
        ['Pratiche di finanziamento', 40, 'pratiche_clienti', null, 'pratica|istruttoria|delibera|finanziamento|cessione del quinto|mutuo'],
        ['Fatture e solleciti', 50, 'amministrativo_contabile', null, 'fattura|sollecito di pagamento|provvigion'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->roles();

            $races = Company::query()->where('vat_number', '05822361007')->orWhere('name', 'like', 'Races%')->first();

            if ($races) {
                $this->equip($races);
            } else {
                $this->command?->warn('Races Finance non trovata: saltata.');
            }
        });
    }

    /** Società dimostrativa con organigramma di prova: solo per sviluppo (vedi FinanceDemoCompanySeeder). */
    public function demo(): void
    {
        DB::transaction(function () {
            $this->roles();

            $demo = Company::query()->updateOrCreate(
                ['vat_number' => '00000000000'],
                [
                    'name' => 'Finance Demo S.r.l.',
                    'vat_name' => 'Finance Demo',
                    'type' => 'FINANCE',
                    'company_type' => 'mediatore',
                    'email' => 'amministrazione@finance-demo.it',
                    'pec' => 'finance-demo@pec.it',
                    'dpo_email' => 'dpo@finance-demo.it',
                    'is_active' => true,
                ],
            );

            $this->staff($demo);
            $this->equip($demo);
        });
    }

    /** Registri e regole: valgono per qualunque società FINANCE. */
    private function equip(Company $company): void
    {
        $this->registers($company);
        $this->rules($company);
    }

    /** Crea solo le funzioni che mancano: quelle esistenti (con i loro codici e la gerarchia) non si toccano mai. */
    private function roles(): void
    {
        foreach (self::ROLES as [$code, $name, $managedBy]) {
            if (DB::table('employee_types')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('employee_types')->insert(['name' => $name, 'code' => $code, 'managed_by_code' => $managedBy, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function staff(Company $company): void
    {
        foreach (self::STAFF as [$name, $email, $roles]) {
            Employee::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => $email],
                ['name' => $name, 'is_active' => true, 'employee_types' => in_array('cda', $roles, true) ? 'cda' : 'dipendente', 'employee_roles' => $roles],
            );
        }
    }

    private function registers(Company $company): void
    {
        foreach (self::REGISTERS as [$code, $name, $roles]) {
            $register = ProtocolRegister::query()->updateOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
            $register->syncRoles($roles);
        }
    }

    private function rules(Company $company): void
    {
        foreach (self::RULES as [$name, $priority, $category, $sender, $subject]) {
            ClassificationRule::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['priority' => $priority, 'category' => $category, 'sender_pattern' => $sender, 'subject_pattern' => $subject, 'operator' => 'or', 'is_active' => true],
            );
        }
    }
}
