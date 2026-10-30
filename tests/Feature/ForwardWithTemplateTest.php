<?php

namespace Tests\Feature;

use App\Mail\ForwardedPec;
use App\Models\Company;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\MailAccount;
use App\Models\ProtocolRegister;
use App\Services\Mail\EmailForwarder;
use App\Services\Protocol\SmistamentoProtocoller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;
use Unico\Core\Mail\OutgoingMailer;
use Unico\Core\Models\EmailTemplate;

class ForwardWithTemplateTest extends TestCase
{
    use UsesAppSchema;

    private Company $company;

    private Employee $mario;

    private IncomingEmail $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        Mail::fake();
        Storage::fake('local');
        config(['media-library.disk_name' => 'local', 'pec.forward.simulation_to' => '']);

        $this->company = Company::create(['name' => 'Alfa']);
        $this->mario = Employee::create(['company_id' => $this->company->id, 'name' => 'Mario', 'email' => 'mario@alfa.it', 'is_active' => true, 'employee_roles' => ['contabile responsabile']]);
        $this->email = IncomingEmail::create(['company_id' => $this->company->id, 'subject' => 'Cartella <1>', 'from_email' => 'ade@pec.it', 'from_name' => 'Agenzia', 'body_text' => 'Corpo del messaggio', 'classification' => 'fiscale', 'received_at' => now(), 'related_type' => 'employee', 'related_id' => $this->mario->id]);
    }

    private function template(array $attrs = []): EmailTemplate
    {
        return EmailTemplate::create($attrs + ['code' => 'pec.inoltro', 'subject' => 'PEC {oggetto} da {mittente}', 'body' => '<p>Ciao {destinatario}, protocollo {protocollo}, categoria {categoria}.</p><a href="{link_presa_in_carico}">vai</a>{testo}', 'is_active' => true, 'app_identifier' => 'unicopec', 'company_id' => null]);
    }

    private function protocol(): void
    {
        $register = ProtocolRegister::create(['company_id' => $this->company->id, 'code' => 'FIS', 'name' => 'Fiscale']);
        $register->syncRoles(['contabile responsabile']);
        app(SmistamentoProtocoller::class)->protocol($this->email);
    }

    public function test_uses_active_template_with_placeholders_replaced(): void
    {
        $this->template();
        $this->protocol();

        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class, function (ForwardedPec $mail) {
            $html = $mail->render();

            return $mail->hasTo('mario@alfa.it')
                && $mail->envelope()->subject === 'PEC Cartella <1> da ade@pec.it'
                && str_contains($html, 'Ciao Mario')
                && str_contains($html, 'protocollo FIS/'.now()->year.'/000001')
                && str_contains($html, 'categoria Fiscale e tributi')
                && str_contains($html, 'Corpo del messaggio');
        });
    }

    public function test_placeholder_values_are_escaped(): void
    {
        $this->template(['subject' => '{oggetto}', 'body' => '<p>{oggetto}</p>']);

        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class, fn (ForwardedPec $m) => str_contains($m->render(), 'Cartella &lt;1&gt;') && ! str_contains($m->render(), 'Cartella <1>'));
    }

    public function test_falls_back_to_default_text_without_template(): void
    {
        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class, fn (ForwardedPec $m) => str_contains($m->render(), 'perché è stata assegnata a te') && $m->envelope()->subject === 'Inoltro email: Cartella <1>');
    }

    public function test_company_template_beats_global(): void
    {
        $this->template(['subject' => 'GLOBALE']);
        $this->template(['subject' => 'DELLA SOCIETÀ', 'company_id' => $this->company->id]);

        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class, fn (ForwardedPec $m) => $m->envelope()->subject === 'DELLA SOCIETÀ');
    }

    public function test_simulation_never_writes_to_real_referent(): void
    {
        // La società 2 non ha dipendenti: usa l'organigramma di ripiego (società 1) e quindi è una simulazione.
        config(['pec.org_fallback_company_id' => $this->company->id, 'pec.forward.simulation_to' => 'prova@test.it']);
        $this->email->forceFill(['company_id' => 2])->save();

        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class, fn (ForwardedPec $m) => $m->hasTo('prova@test.it') && ! $m->hasTo('mario@alfa.it'));
    }

    public function test_sends_through_core_mailer_when_company_has_smtp_account(): void
    {
        MailAccount::create(['company_id' => $this->company->id, 'name' => 'Casella', 'email_address' => 'box@alfa.it', 'is_active' => true, 'smtp_host' => 'smtp.alfa.it', 'smtp_port' => 587, 'smtp_password' => 'segreta']);
        $core = Mockery::mock(OutgoingMailer::class);
        $core->shouldReceive('send')->once()->withArgs(fn ($account, $to, $mailable) => $account->email_address === 'box@alfa.it' && $account->hasSmtpConfigured() && $to === 'mario@alfa.it' && $mailable instanceof ForwardedPec);
        $this->app->instance(OutgoingMailer::class, $core);

        $result = app(EmailForwarder::class)->forward($this->email->fresh());

        $this->assertSame('ok', $result['status']);
        Mail::assertNothingSent();
    }

    public function test_attachments_still_follow_size_limit(): void
    {
        $this->email->addMediaFromString('piccolo')->usingFileName('a.txt')->toMediaCollection(IncomingEmail::ATTACHMENTS);
        $this->assertCount(1, (new ForwardedPec($this->email->fresh(), $this->mario))->attachments());

        // La media library accetta file fino a 10 MB: due da 8 MB superano il limite di 15 MB dell'inoltro.
        foreach (['uno.bin', 'due.bin'] as $name) {
            $this->email->addMediaFromString(str_repeat('a', 8 * 1024 * 1024))->usingFileName($name)->toMediaCollection(IncomingEmail::ATTACHMENTS);
        }
        $this->assertCount(0, (new ForwardedPec($this->email->fresh(), $this->mario))->attachments());
    }

    public function test_template_lookup_failure_falls_back_to_default_text(): void
    {
        config(['unico-core.connection' => 'core']); // come in produzione: connessione non configurata

        $result = app(EmailForwarder::class)->forward($this->email->fresh());

        $this->assertSame('ok', $result['status']);
        Mail::assertSent(ForwardedPec::class, fn (ForwardedPec $m) => str_contains($m->render(), 'perché è stata assegnata a te'));
    }

    public function test_default_text_shows_the_protocol_number(): void
    {
        $this->protocol();

        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class, fn (ForwardedPec $m) => str_contains($m->render(), 'FIS/'.now()->year.'/000001'));
    }

    public function test_pec_only_company_forwards_with_the_app_mailer(): void
    {
        MailAccount::create(['company_id' => $this->company->id, 'name' => 'PEC', 'email_address' => 'pec@alfa.it', 'is_pec' => true, 'is_active' => true, 'smtp_host' => 'smtp.pec.it', 'smtp_port' => 465, 'smtp_password' => 'x']);
        $core = Mockery::mock(OutgoingMailer::class);
        $core->shouldNotReceive('send');
        $this->app->instance(OutgoingMailer::class, $core);

        app(EmailForwarder::class)->forward($this->email->fresh());

        Mail::assertSent(ForwardedPec::class);
    }
}
