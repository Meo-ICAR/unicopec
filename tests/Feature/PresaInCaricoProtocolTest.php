<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use App\Services\Mail\EmailHandover;
use App\Services\Protocol\SmistamentoProtocoller;
use Illuminate\Support\Facades\URL;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class PresaInCaricoProtocolTest extends TestCase
{
    use UsesAppSchema;

    private Company $company;

    private Employee $mario;

    private IncomingEmail $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        $this->company = Company::create(['name' => 'Alfa']);
        $this->mario = $this->employee('Mario', ['contabile responsabile']);
        $this->email = IncomingEmail::create(['company_id' => $this->company->id, 'subject' => 'Cartella', 'from_email' => 'a@b.it', 'classification' => 'fiscale', 'received_at' => now(), 'related_type' => 'employee', 'related_id' => $this->mario->id]);
    }

    private function employee(string $name, array $roles): Employee
    {
        return Employee::create(['company_id' => $this->company->id, 'name' => $name, 'email' => strtolower($name).'@a.it', 'is_active' => true, 'employee_roles' => $roles]);
    }

    private function register(string $code, array $roles, bool $active = true): ProtocolRegister
    {
        $r = ProtocolRegister::create(['company_id' => $this->company->id, 'code' => $code, 'name' => $code, 'is_active' => $active]);
        $r->syncRoles($roles);

        return $r;
    }

    private function url(?Employee $employee = null): string
    {
        return URL::temporarySignedRoute('pec.take', now()->addDay(), ['email' => $this->email->id, 'employee' => ($employee ?? $this->mario)->id]);
    }

    public function test_post_with_chosen_register_takes_charge_and_creates_second_protocol(): void
    {
        $fis = $this->register('FIS', ['contabile responsabile']);
        $this->register('GEN', ['contabile responsabile']);
        app(SmistamentoProtocoller::class)->protocol($this->email);

        $this->post($this->url(), ['register_id' => $fis->id])->assertOk()->assertSee('Fatto');

        $second = $this->email->protocols()->where('type', Protocol::PRESA_IN_CARICO)->first();
        $this->assertSame($fis->id, $second->protocol_register_id);
        $this->assertSame($this->mario->id, $second->employee_id);
        $this->assertSame($this->email->protocols()->where('type', Protocol::SMISTAMENTO)->first()->id, $second->parent_protocol_id);
        $this->assertNotNull($this->email->fresh()->taken_at);
    }

    public function test_get_does_not_take_charge_or_consume_number(): void
    {
        $this->register('FIS', ['contabile responsabile']);

        $this->get($this->url())->assertOk();

        $this->assertNull($this->email->fresh()->taken_at);
        $this->assertSame(0, Protocol::count());
    }

    public function test_single_register_is_preselected_in_view(): void
    {
        $fis = $this->register('FIS', ['contabile responsabile']);

        $this->get($this->url())->assertSee('name="register_id"', false)->assertSee('value="'.$fis->id.'" selected', false);
    }

    public function test_several_registers_require_a_choice(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $this->register('GEN', ['contabile responsabile']);

        $this->post($this->url())->assertOk()->assertSee('Scegli il registro');

        $this->assertNull($this->email->fresh()->taken_at);
        $this->assertSame(0, Protocol::count());
    }

    public function test_register_not_allowed_for_the_person_is_rejected(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $legale = $this->register('LEG', ['legale']);

        $this->post($this->url(), ['register_id' => $legale->id])->assertOk()->assertSee('non è consentito');

        $this->assertNull($this->email->fresh()->taken_at);
        $this->assertSame(0, Protocol::count());
    }

    public function test_person_without_registers_gets_message_and_nothing_changes(): void
    {
        $this->register('LEG', ['legale']);

        $this->get($this->url())->assertSee('amministratore')->assertDontSee('Prendo in carico</button>', false);
        $this->post($this->url())->assertOk()->assertSee('amministratore');

        $this->assertNull($this->email->fresh()->taken_at);
        $this->assertSame(0, Protocol::count());
    }

    public function test_company_without_any_register_keeps_the_old_behaviour(): void
    {
        $this->post($this->url())->assertOk()->assertSee('Fatto');

        $this->assertNotNull($this->email->fresh()->taken_at);
        $this->assertSame(0, Protocol::count());
    }

    public function test_second_take_reports_who_took_it_and_creates_one_protocol_only(): void
    {
        $fis = $this->register('FIS', ['contabile responsabile']);
        $luca = $this->employee('Luca', ['contabile responsabile']);

        $first = app(EmailHandover::class)->take($this->email, $this->mario, 'link', $fis->id);
        $second = app(EmailHandover::class)->take($this->email, $luca, 'link', $fis->id);

        $this->assertTrue($first['taken']);
        $this->assertFalse($second['taken']);
        $this->assertSame($this->mario->id, $second['by']->id);
        $this->assertSame(1, Protocol::where('type', Protocol::PRESA_IN_CARICO)->count());
        $this->assertSame(1, Protocol::count() - Protocol::where('type', Protocol::SMISTAMENTO)->count());
    }

    public function test_admin_from_panel_can_choose_any_company_register(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $legale = $this->register('LEG', ['legale']);

        $result = app(EmailHandover::class)->take($this->email, $this->mario, 'manuale', $legale->id, 7);

        $this->assertTrue($result['taken']);
        $this->assertSame($legale->id, $result['protocol']->protocol_register_id);
        $this->assertSame(7, $result['protocol']->created_by);
    }

    public function test_inactive_register_cannot_be_chosen(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $off = $this->register('OFF', ['contabile responsabile'], active: false);

        $result = app(EmailHandover::class)->take($this->email, $this->mario, 'link', $off->id);

        $this->assertFalse($result['taken']);
        $this->assertNotNull($result['error']);
        $this->assertSame(0, Protocol::count());
    }
}
