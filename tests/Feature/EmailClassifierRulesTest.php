<?php

namespace Tests\Feature;

use App\Enums\EmailCategory;
use App\Models\ClassificationRule;
use App\Models\Company;
use App\Models\IncomingEmail;
use App\Services\Mail\EmailClassifier;
use Illuminate\Support\Facades\Http;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class EmailClassifierRulesTest extends TestCase
{
    use UsesAppSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        config(['pec.ai.key' => '']);
        ClassificationRule::query()->delete();
    }

    private function email(array $attrs = []): IncomingEmail
    {
        return IncomingEmail::create($attrs + ['company_id' => 1, 'from_email' => 'x@y.it', 'subject' => 'Avviso', 'body_text' => 'testo']);
    }

    private function rule(array $attrs): ClassificationRule
    {
        return ClassificationRule::create($attrs + ['name' => 'r', 'priority' => 100, 'is_active' => true, 'category' => 'legale', 'operator' => 'or', 'subject_pattern' => 'Avviso']);
    }

    public function test_company_rule_beats_global_with_same_priority(): void
    {
        $this->rule(['company_id' => null, 'category' => 'legale']);
        $mine = $this->rule(['company_id' => 1, 'category' => 'fiscale']);

        $result = app(EmailClassifier::class)->classify($this->email());

        $this->assertSame(EmailCategory::Fiscale, $result->category);
        $this->assertSame($mine->id, $result->ruleId);
        $this->assertSame('rules', $result->source);
    }

    public function test_lower_priority_number_wins(): void
    {
        $this->rule(['priority' => 50, 'category' => 'legale']);
        $first = $this->rule(['priority' => 10, 'category' => 'fiscale']);

        $this->assertSame($first->id, app(EmailClassifier::class)->classify($this->email())->ruleId);
    }

    public function test_inactive_rule_is_skipped(): void
    {
        $this->rule(['is_active' => false]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['category' => 'altro', 'urgency' => 'bassa', 'summary' => 's', 'confidence' => 0.5])]]])]);
        config(['pec.ai.key' => 'k']);

        $result = app(EmailClassifier::class)->classify($this->email());

        $this->assertSame('ai', $result->source);
        $this->assertNull($result->ruleId);
    }

    public function test_rule_of_another_company_is_skipped(): void
    {
        $this->rule(['company_id' => 2]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['category' => 'altro', 'urgency' => 'bassa', 'summary' => 's', 'confidence' => 0.5])]]])]);
        config(['pec.ai.key' => 'k']);

        $this->assertSame('ai', app(EmailClassifier::class)->classify($this->email())->source);
    }

    public function test_rule_match_without_ai_key_falls_back_to_normal_urgency(): void
    {
        $this->rule([]);

        $result = app(EmailClassifier::class)->classify($this->email());

        $this->assertSame('normale', $result->urgency);
        $this->assertSame('Avviso', $result->summary);
    }

    public function test_provider_notification_still_wins_first(): void
    {
        $this->rule([]);

        $result = app(EmailClassifier::class)->classify($this->email(['classification' => 'provider_notification']));

        $this->assertSame(EmailCategory::ProviderNotification, $result->category);
        $this->assertNull($result->ruleId);
    }

    public function test_seeded_rules_keep_the_old_behaviour(): void
    {
        (require database_path('migrations/2026_10_30_100000_create_classification_rules_table.php'))->insertDefaults();

        $result = app(EmailClassifier::class)->classify($this->email(['from_email' => 'x@pec.agenziariscossione.gov.it']));

        $this->assertSame(EmailCategory::Fiscale, $result->category);
    }

    public function test_pipeline_stores_rule_id_and_names_the_rule_in_the_event(): void
    {
        $rule = $this->rule(['name' => 'Avvisi']);
        $email = $this->email();

        $pipeline = app(\App\Services\Mail\EmailPipeline::class);
        $pipeline->classified($email, app(EmailClassifier::class)->classify($email));

        $this->assertSame($rule->id, $email->fresh()->classification_rule_id);
        $this->assertStringContainsString('«Avvisi»', $email->events()->first()->message);
    }
}
