<?php

namespace Tests\Feature;

use App\Jobs\ArchiveEmail;
use App\Models\Company;
use App\Models\EmailEvent;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use App\Services\Archive\ArchivePackage;
use App\Services\Archive\ArchiveService;
use App\Services\Archive\FakeArchiveService;
use App\Services\Mail\EmailAnomalies;
use App\Services\Mail\EmailHandover;
use App\Services\Protocol\SmistamentoProtocoller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class RecordingArchive implements ArchiveService
{
    /** @var array<int, ArchivePackage> */
    public array $sent = [];

    public bool $fail = false;

    public function send(ArchivePackage $package): string
    {
        if ($this->fail) {
            throw new RuntimeException('servizio non raggiungibile');
        }

        $this->sent[] = $package;

        return 'REF-'.count($this->sent);
    }
}

class ArchiveTest extends TestCase
{
    use UsesAppSchema;

    private Company $company;

    private Employee $mario;

    private IncomingEmail $email;

    private ProtocolRegister $register;

    private RecordingArchive $archive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        Storage::fake('local');
        config(['media-library.disk_name' => 'local']);
        $this->archive = new RecordingArchive;
        $this->app->instance(ArchiveService::class, $this->archive);

        $this->company = Company::create(['name' => 'Alfa']);
        $this->mario = Employee::create(['company_id' => $this->company->id, 'name' => 'Mario', 'email' => 'm@a.it', 'is_active' => true, 'employee_roles' => ['contabile responsabile']]);
        $this->register = ProtocolRegister::create(['company_id' => $this->company->id, 'code' => 'FIS', 'name' => 'Fiscale']);
        $this->register->syncRoles(['contabile responsabile']);
        $this->email = IncomingEmail::create(['company_id' => $this->company->id, 'subject' => 'Cartella', 'from_email' => 'a@b.it', 'classification' => 'fiscale', 'received_at' => now(), 'related_type' => 'employee', 'related_id' => $this->mario->id]);
    }

    private function smistamento(): void
    {
        app(SmistamentoProtocoller::class)->protocol($this->email);
    }

    public function test_smistamento_marks_the_email_da_archiviare(): void
    {
        $this->smistamento();

        $this->assertSame('da_archiviare', $this->email->fresh()->archive_status);
    }

    public function test_package_contains_sha256_of_every_attachment(): void
    {
        $this->email->addMediaFromString('contenuto uno')->usingFileName('a.pdf')->toMediaCollection(IncomingEmail::ATTACHMENTS);
        $this->smistamento();

        $package = ArchivePackage::for($this->email->fresh());

        $this->assertCount(1, $package->files);
        $this->assertSame('a.pdf', $package->files[0]['name']);
        $this->assertSame(hash('sha256', 'contenuto uno'), $package->files[0]['sha256']);
        $this->assertSame('FIS/'.now()->year.'/000001', $package->metadata['protocols'][0]['identifier']);
        $this->assertFalse($package->supplement);
    }

    public function test_package_declares_missing_eml_without_failing(): void
    {
        $this->smistamento();

        $package = ArchivePackage::for($this->email->fresh());

        $this->assertFalse($package->metadata['eml_available']);
    }

    public function test_take_dispatches_archive_job_and_marks_archiviata_with_reference(): void
    {
        $this->smistamento();

        app(EmailHandover::class)->take($this->email, $this->mario, 'link', $this->register->id);

        $email = $this->email->fresh();
        $this->assertSame('archiviata', $email->archive_status);
        $this->assertSame('REF-1', $email->archive_reference);
        $this->assertNotNull($email->archived_at);
        $this->assertCount(1, $this->archive->sent);
        $this->assertCount(2, $this->archive->sent[0]->metadata['protocols']);
        $this->assertFalse($this->archive->sent[0]->supplement);
    }

    public function test_failure_after_max_tries_sets_errore_and_records_anomaly(): void
    {
        $this->smistamento();
        $this->archive->fail = true;
        $job = new ArchiveEmail($this->email->id);

        $this->assertSame(5, $job->tries);

        try {
            $job->handle($this->archive);
            $this->fail('Atteso un errore.');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $this->assertSame('errore', $this->email->fresh()->archive_status);
        $this->assertTrue(EmailEvent::where('incoming_email_id', $this->email->id)->where('kind', EmailEvent::ANOMALY)->where('status', 'error')->exists());
        $this->assertTrue(IncomingEmail::query()->withAnomalies()->whereKey($this->email->id)->exists());
    }

    public function test_pending_command_archives_old_smistamento_only_emails(): void
    {
        Queue::fake();
        $this->smistamento();

        Carbon::setTestNow(now()->addDays(3));
        Artisan::call('protocol:archive-pending');
        Queue::assertNothingPushed();

        Carbon::setTestNow(now()->addDays(5));
        Artisan::call('protocol:archive-pending');
        Queue::assertPushed(ArchiveEmail::class, fn ($j) => $j->emailId === $this->email->id);
    }

    public function test_pending_command_skips_emails_already_taken_and_archived(): void
    {
        Queue::fake();
        $this->smistamento();
        $this->email->forceFill(['archive_status' => 'archiviata'])->save();

        Carbon::setTestNow(now()->addDays(30));
        Artisan::call('protocol:archive-pending');

        Queue::assertNothingPushed();
    }

    public function test_late_take_after_archive_sends_supplement_once(): void
    {
        $this->smistamento();
        (new ArchiveEmail($this->email->id))->handle($this->archive);
        $this->assertCount(1, $this->archive->sent);
        $this->assertFalse($this->archive->sent[0]->supplement);

        app(EmailHandover::class)->take($this->email, $this->mario, 'link', $this->register->id);
        (new ArchiveEmail($this->email->id))->handle($this->archive);

        $this->assertCount(2, $this->archive->sent);
        $this->assertTrue($this->archive->sent[1]->supplement);
        $this->assertSame('archiviata', $this->email->fresh()->archive_status);
    }

    public function test_job_does_nothing_when_everything_is_already_archived(): void
    {
        $this->smistamento();
        (new ArchiveEmail($this->email->id))->handle($this->archive);
        (new ArchiveEmail($this->email->id))->handle($this->archive);

        $this->assertCount(1, $this->archive->sent);
    }

    public function test_fake_service_sends_nothing_outside_the_app(): void
    {
        Http::fake();
        $this->smistamento();

        $reference = (new FakeArchiveService)->send(ArchivePackage::for($this->email->fresh()));

        $this->assertStringStartsWith('FAKE-', $reference);
        Http::assertNothingSent();
    }
}
