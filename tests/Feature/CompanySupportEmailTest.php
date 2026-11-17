<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class CompanySupportEmailTest extends TestCase
{
    use UsesAppSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();

        $this->company = Company::create(['name' => 'Alfa']);
        $admin = new User(['name' => 'A', 'email' => 'a@a.it', 'password' => 'x']);
        $admin->forceFill(['role' => 'super_admin', 'company_id' => $this->company->id])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->company);
    }

    public function test_l_email_di_assistenza_si_salva_dalla_scheda_societa(): void
    {
        Livewire::test(EditCompany::class, ['record' => $this->company->id])
            ->fillForm(['name' => 'Alfa', 'unicopec_support_email' => 'assistenza@alfa.it'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('assistenza@alfa.it', $this->company->fresh()->unicopec_support_email);
    }

    public function test_l_email_di_assistenza_deve_essere_un_indirizzo_valido(): void
    {
        Livewire::test(EditCompany::class, ['record' => $this->company->id])
            ->fillForm(['name' => 'Alfa', 'unicopec_support_email' => 'non-un-indirizzo'])
            ->call('save')
            ->assertHasFormErrors(['unicopec_support_email' => 'email']);
    }
}
