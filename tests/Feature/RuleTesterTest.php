<?php

namespace Tests\Feature;

use App\Models\ClassificationRule;
use App\Models\IncomingEmail;
use App\Services\Mail\RuleTester;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class RuleTesterTest extends TestCase
{
    use UsesAppSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
    }

    private function email(int $company, string $subject, ?string $classification = null): IncomingEmail
    {
        return IncomingEmail::create(['company_id' => $company, 'subject' => $subject, 'from_email' => 'a@b.it', 'received_at' => now(), 'classification' => $classification]);
    }

    public function test_returns_only_matching_emails_of_the_rule_company(): void
    {
        $this->email(1, 'Cartella esattoriale');
        $this->email(1, 'Altro');
        $this->email(2, 'Cartella di un altro');
        $rule = new ClassificationRule(['company_id' => 1, 'operator' => 'or', 'subject_pattern' => 'cartella']);

        $found = app(RuleTester::class)->sample($rule);

        $this->assertSame(['Cartella esattoriale'], $found->pluck('subject')->all());
    }

    public function test_global_rule_looks_at_all_companies(): void
    {
        $this->email(1, 'Cartella uno');
        $this->email(2, 'Cartella due');
        $rule = new ClassificationRule(['company_id' => null, 'operator' => 'or', 'subject_pattern' => 'cartella']);

        $this->assertCount(2, app(RuleTester::class)->sample($rule));
    }

    public function test_does_not_write_anything_and_skips_provider_receipts(): void
    {
        $this->email(1, 'Cartella ricevuta', 'provider_notification');
        $this->email(1, 'Cartella vera');
        $rule = new ClassificationRule(['company_id' => 1, 'operator' => 'or', 'subject_pattern' => 'cartella']);

        $found = app(RuleTester::class)->sample($rule);

        $this->assertSame(['Cartella vera'], $found->pluck('subject')->all());
        $this->assertNull(IncomingEmail::where('subject', 'Cartella vera')->first()->classification);
    }

    public function test_respects_limit(): void
    {
        foreach (range(1, 5) as $i) {
            $this->email(1, "Cartella $i");
        }
        $rule = new ClassificationRule(['company_id' => 1, 'operator' => 'or', 'subject_pattern' => 'cartella']);

        $this->assertCount(3, app(RuleTester::class)->sample($rule, 3));
    }
}
