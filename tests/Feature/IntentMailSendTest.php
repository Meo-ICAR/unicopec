<?php

namespace Tests\Feature;

use App\Jobs\SendBpmEvent;
use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Services\Mail\EmailIntentExtractor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

/** `mail:intent --send` comunica a UnicoBPM solo le richieste in pec.bpm_intents, e non rilegge le email già lette dall'AI. */
class IntentMailSendTest extends TestCase
{
    use UsesAppSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
    }

    private function email(string $subject): IncomingEmail
    {
        return IncomingEmail::create(['company_id' => 1, 'from_email' => 'legale@studio.it', 'subject' => $subject, 'body_text' => 'testo', 'received_at' => now()]);
    }

    /** @param array<string, mixed> $entities */
    private function extractorReturning(string $intent, array $entities = []): void
    {
        $this->mock(EmailIntentExtractor::class, fn ($mock) => $mock->shouldReceive('extract')->andReturn([
            'intent' => $intent, 'confidence' => 0.97, 'summary' => 'sintesi', 'entities' => $entities, 'flags' => [],
        ]));
    }

    public function test_l_audit_periodico_parte_verso_unicobpm_con_i_produttori(): void
    {
        Queue::fake();
        $this->extractorReturning('audit_periodico', ['produttori' => ['Sajeva'], 'produttori_ids' => [117], 'produttori_trovati' => [['id' => 117, 'nome' => 'SAJEVA PAOLO', 'email' => 'p@x.it']]]);
        $email = $this->email('Verifiche ispettive 1/5 rete');

        Artisan::call('mail:intent', ['email' => $email->id, '--send' => true, '--save' => true]);

        Queue::assertPushed(SendBpmEvent::class, fn (SendBpmEvent $job) => $job->event['event'] === 'email.richiesta'
            && $job->event['data']['richiesta'] === 'audit_periodico'
            && $job->event['data']['produttori_ids'] === '117'
            && $job->emailId === $email->id);
        $this->assertSame(1, EmailEvent::where('kind', EmailEvent::INTENT)->count());
    }

    public function test_le_altre_richieste_non_partono_anche_con_send(): void
    {
        Queue::fake();
        $this->extractorReturning('rettifica_iban', ['iban' => 'IT60X0542811101000000123456']);

        Artisan::call('mail:intent', ['email' => $this->email('Nuovo IBAN')->id, '--send' => true, '--save' => true]);

        Queue::assertNothingPushed();
    }

    public function test_senza_send_non_parte_nulla(): void
    {
        Queue::fake();
        $this->extractorReturning('audit_periodico');

        Artisan::call('mail:intent', ['email' => $this->email('Verifiche ispettive')->id, '--save' => true]);

        Queue::assertNothingPushed();
    }

    public function test_le_email_gia_lette_dall_ai_non_si_rileggono(): void
    {
        Queue::fake();
        $this->mock(EmailIntentExtractor::class, fn ($mock) => $mock->shouldReceive('extract')->once()->andReturn([
            'intent' => 'nessuna', 'confidence' => 1.0, 'summary' => '', 'entities' => [], 'flags' => [],
        ]));
        $read = $this->email('già letta');
        EmailEvent::record($read, EmailEvent::INTENT, 'ok', 'Richiesta: nessuna', []);
        $this->email('nuova');

        Artisan::call('mail:intent', ['--save' => true]);
    }
}
