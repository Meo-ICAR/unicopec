<?php

namespace Tests\Feature;

use App\Filament\Resources\ProtocolRegisters\Pages\CreateProtocolRegister;
use App\Filament\Resources\ProtocolRegisters\Pages\EditProtocolRegister;
use App\Filament\Resources\Protocols\Pages\ListProtocols;
use App\Filament\Resources\Protocols\ProtocolResource;
use App\Models\Company;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use App\Models\User;
use App\Services\Protocol\ProtocolNumberer;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class ProtocolRegisterResourceTest extends TestCase
{
    use UsesAppSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        DB::table('employee_types')->insert([['name' => 'legale'], ['name' => 'contabile responsabile']]);

        $this->company = Company::create(['name' => 'Alfa']);
        $admin = new User(['name' => 'A', 'email' => 'a@a.it', 'password' => 'x']);
        $admin->forceFill(['role' => 'admin', 'company_id' => $this->company->id])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->company);
    }

    public function test_register_form_requires_code_and_name(): void
    {
        Livewire::test(CreateProtocolRegister::class)->fillForm(['code' => '', 'name' => ''])->call('create')->assertHasFormErrors(['code' => 'required', 'name' => 'required']);
    }

    public function test_code_is_unique_per_company(): void
    {
        ProtocolRegister::create(['company_id' => $this->company->id, 'code' => 'FIS', 'name' => 'Fiscale']);
        ProtocolRegister::create(['company_id' => 999, 'code' => 'LEG', 'name' => 'Altra società']);

        Livewire::test(CreateProtocolRegister::class)->fillForm(['code' => 'FIS', 'name' => 'Doppio'])->call('create')->assertHasFormErrors(['code']);
        Livewire::test(CreateProtocolRegister::class)->fillForm(['code' => 'LEG', 'name' => 'Legale'])->call('create')->assertHasNoFormErrors();
    }

    public function test_register_links_roles_from_employee_types_on_create_and_edit(): void
    {
        Livewire::test(CreateProtocolRegister::class)->fillForm(['code' => 'fis', 'name' => 'Fiscale', 'is_active' => true, 'roles' => ['contabile responsabile']])->call('create')->assertHasNoFormErrors();

        $register = ProtocolRegister::where('code', 'FIS')->firstOrFail();
        $this->assertSame($this->company->id, $register->company_id);
        $this->assertSame(['contabile responsabile'], $register->roleNames());

        Livewire::test(EditProtocolRegister::class, ['record' => $register->id])->assertFormSet(['roles' => ['contabile responsabile']])
            ->fillForm(['roles' => ['legale', 'contabile responsabile']])->call('save')->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['legale', 'contabile responsabile'], $register->fresh()->roleNames());
    }

    public function test_protocol_list_is_read_only_and_company_scoped(): void
    {
        $mine = ProtocolRegister::create(['company_id' => $this->company->id, 'code' => 'FIS', 'name' => 'Fiscale']);
        $other = ProtocolRegister::create(['company_id' => 999, 'code' => 'FIS', 'name' => 'Altrui']);
        $emailMine = IncomingEmail::create(['company_id' => $this->company->id, 'subject' => 'x']);
        $emailOther = IncomingEmail::create(['company_id' => 999, 'subject' => 'y']);
        $p1 = app(ProtocolNumberer::class)->assign($mine, $emailMine, Protocol::SMISTAMENTO, 'legale', null);
        $p2 = app(ProtocolNumberer::class)->assign($other, $emailOther, Protocol::SMISTAMENTO, 'legale', null);

        Livewire::test(ListProtocols::class)->assertCanSeeTableRecords([$p1])->assertCanNotSeeTableRecords([$p2]);

        $this->assertFalse(ProtocolResource::canCreate());
        $this->assertFalse(ProtocolResource::canEdit($p1));
        $this->assertFalse(ProtocolResource::canDelete($p1));
    }
}
