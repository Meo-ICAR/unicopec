<?php

namespace Tests\Feature;

use App\Models\IncomingEmail;
use App\Services\Mail\EmailClassifier;
use App\Services\Mail\EmailIntentExtractor;
use App\Services\Mail\InvoiceProformaChecker;
use Illuminate\Support\Facades\Http;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

/** Le ricevute PEC (accettazione, consegna, errori) non hanno testo da analizzare: non devono mai arrivare al modello di AI. */
class ProviderNotificationNotSentToAiTest extends TestCase
{
    use UsesAppSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        config(['pec.ai.key' => 'chiave-di-prova']);
    }

    private function receipt(): IncomingEmail
    {
        return IncomingEmail::create([
            'company_id' => 1,
            'from_email' => 'posta-certificata@pec.aruba.it',
            'subject' => 'ACCETTAZIONE: Proforma 927 - Totale: 1.440,00',
            'body_text' => 'Ricevuta di accettazione',
            'classification' => 'provider_notification',
        ]);
    }

    public function test_la_ricevuta_non_e_classificata_con_l_ai(): void
    {
        Http::fake();

        $result = app(EmailClassifier::class)->classify($this->receipt());

        $this->assertSame('provider_notification', $result->category->value);
        Http::assertNothingSent();
    }

    public function test_la_ricevuta_non_e_inviata_all_estrazione_della_richiesta(): void
    {
        Http::fake();

        $result = app(EmailIntentExtractor::class)->extract($this->receipt());

        $this->assertSame('nessuna', $result['intent']);
        Http::assertNothingSent();
    }

    public function test_la_ricevuta_con_totale_nell_oggetto_non_va_al_controllo_fatture(): void
    {
        Http::fake();

        $result = app(InvoiceProformaChecker::class)->check($this->receipt());

        $this->assertSame([], $result['fatture']);
        Http::assertNothingSent();
    }

    public function test_un_messaggio_normale_arriva_all_estrazione(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'intent' => 'nessuna', 'confidence' => 0.9, 'summary' => 'Ringraziamento', 'proforma' => null, 'iban' => null, 'beneficiary' => null, 'amount' => null, 'practice' => null,
        ])]]])]);

        $email = IncomingEmail::create(['company_id' => 1, 'from_email' => 'agente@esempio.it', 'subject' => 'Grazie', 'body_text' => 'Grazie mille']);

        app(EmailIntentExtractor::class)->extract($email);

        Http::assertSentCount(1);
    }
}
