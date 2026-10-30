<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use App\Services\Mail\EmailAnomalies;
use App\Services\Mail\EmailPipeline;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class SmistamentoProtocolTest extends TestCase
{
    use UsesAppSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        config(['services.bpm.url' => null]);
        $this->company = Company::create(['name' => 'Alfa']);
        $this->employee = Employee::create(['company_id' => $this->company->id, 'name' => 'Mario', 'email' => 'm@a.it', 'is_active' => true, 'employee_roles' => ['contabile responsabile']]);
    }

    private Company $company;

    private Employee $employee;

    private function register(string $code, array $roles, bool $active = true): ProtocolRegister
    {
        $r = ProtocolRegister::create(['company_id' => $this->company->id, 'code' => $code, 'name' => $code, 'is_active' => $active]);
        $r->syncRoles($roles);

        return $r;
    }

    private function email(string $classification = 'fiscale', bool $routed = true): IncomingEmail
    {
        return IncomingEmail::create([
            'company_id' => $this->company->id, 'subject' => 'Cartella', 'from_email' => 'a@b.it', 'classification' => $classification, 'received_at' => now(),
            'related_type' => $routed ? 'employee' : null, 'related_id' => $routed ? $this->employee->id : null,
        ]);
    }

    private function route(IncomingEmail $email): void
    {
        app(EmailPipeline::class)->routed($email, ['employee' => $this->employee, 'role' => 'contabile responsabile', 'via' => 'ruolo diretto', 'org_company_id' => $this->company->id]);
    }

    public function test_routed_email_gets_smistamento_protocol_in_first_register_of_the_function(): void
    {
        $this->register('ZZZ', ['contabile responsabile']);
        $this->register('FIS', ['contabile responsabile']);
        $email = $this->email(routed: false);

        $this->route($email);

        $protocol = $email->protocols()->first();
        $this->assertSame('FIS/'.now()->year.'/000001', $protocol->identifier);
        $this->assertSame(Protocol::SMISTAMENTO, $protocol->type);
        $this->assertSame('contabile responsabile', $protocol->role);
        $this->assertTrue(EmailEvent::where('incoming_email_id', $email->id)->where('kind', EmailEvent::PROTOCOLLED)->exists());
    }

    public function test_second_routing_reuses_the_protocol(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $email = $this->email(routed: false);

        $this->route($email);
        $this->route($email->fresh());

        $this->assertSame(1, $email->protocols()->count());
    }

    public function test_category_without_role_gets_no_protocol(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $email = $this->email('non_rilevante');

        $this->assertNull(app(\App\Services\Protocol\SmistamentoProtocoller::class)->protocol($email));
        $this->assertSame(0, $email->protocols()->count());
    }

    public function test_function_without_register_records_anomaly_and_no_number(): void
    {
        $this->register('LEG', ['legale']);
        $email = $this->email(routed: false);

        $this->route($email);

        $this->assertSame(0, Protocol::count());
        $event = EmailEvent::where('incoming_email_id', $email->id)->where('kind', EmailEvent::ANOMALY)->first();
        $this->assertStringContainsString('Nessun registro di protocollo per la funzione «contabile responsabile»', $event->message);
        $this->assertSame('error', $event->status);
    }

    public function test_inactive_register_is_not_used(): void
    {
        $this->register('FIS', ['contabile responsabile'], active: false);
        $email = $this->email(routed: false);

        $this->route($email);

        $this->assertSame(0, Protocol::count());
    }

    public function test_backfill_protocols_already_routed_emails(): void
    {
        $this->register('FIS', ['contabile responsabile']);
        $routed = $this->email();
        $notRouted = $this->email(routed: false);
        $receipt = $this->email('provider_notification');

        Artisan::call('protocol:backfill');

        $this->assertSame(1, $routed->protocols()->count());
        $this->assertSame(0, $notRouted->protocols()->count());
        $this->assertSame(0, $receipt->protocols()->count());
    }

    public function test_anomaly_includes_routed_email_without_protocol(): void
    {
        $email = $this->email();

        $this->assertContains('Smistata ma senza protocollo', EmailAnomalies::for($email->load('events')));
        $this->assertTrue(IncomingEmail::query()->withAnomalies()->whereKey($email->id)->exists());

        $this->register('FIS', ['contabile responsabile']);
        Artisan::call('protocol:backfill');

        $this->assertNotContains('Smistata ma senza protocollo', EmailAnomalies::for($email->fresh()->load('events')));
        $this->assertFalse(IncomingEmail::query()->withAnomalies()->whereKey($email->id)->exists());
    }
}
