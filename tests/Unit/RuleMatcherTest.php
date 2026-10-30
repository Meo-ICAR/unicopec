<?php

namespace Tests\Unit;

use App\Enums\EmailCategory;
use App\Models\ClassificationRule;
use App\Services\Mail\RuleMatcher;
use Tests\TestCase;

class RuleMatcherTest extends TestCase
{
    private function rule(array $attrs): ClassificationRule
    {
        return new ClassificationRule($attrs + ['category' => EmailCategory::Fiscale, 'operator' => 'or']);
    }

    public function test_or_matches_when_any_condition_matches(): void
    {
        $rule = $this->rule(['sender_pattern' => 'agenziaentrate\.gov\.it$', 'subject_pattern' => 'cartella']);

        $this->assertTrue((new RuleMatcher)->matches($rule, 'x@altro.it', 'Una cartella', ''));
        $this->assertTrue((new RuleMatcher)->matches($rule, 'x@agenziaentrate.gov.it', 'ciao', ''));
        $this->assertFalse((new RuleMatcher)->matches($rule, 'x@altro.it', 'ciao', ''));
    }

    public function test_and_requires_all_filled_conditions(): void
    {
        $rule = $this->rule(['operator' => 'and', 'sender_pattern' => 'agenziaentrate\.gov\.it$', 'subject_pattern' => 'cartella']);

        $this->assertTrue((new RuleMatcher)->matches($rule, 'x@agenziaentrate.gov.it', 'Cartella 2026', ''));
        $this->assertFalse((new RuleMatcher)->matches($rule, 'x@agenziaentrate.gov.it', 'ciao', ''));
    }

    public function test_case_insensitive(): void
    {
        $rule = $this->rule(['body_pattern' => 'GARANTE']);

        $this->assertTrue((new RuleMatcher)->matches($rule, '', '', 'dal garante per la privacy'));
    }

    public function test_empty_rule_never_matches(): void
    {
        $this->assertFalse((new RuleMatcher)->matches($this->rule([]), 'a@b.it', 'x', 'y'));
        $this->assertFalse((new RuleMatcher)->matches($this->rule(['operator' => 'and']), 'a@b.it', 'x', 'y'));
    }

    public function test_invalid_pattern_is_no_match_and_does_not_throw(): void
    {
        $rule = $this->rule(['subject_pattern' => '(non chiusa']);

        $this->assertFalse((new RuleMatcher)->matches($rule, '', 'non chiusa', ''));
    }

    public function test_validate_pattern_rejects_over_500_chars_and_bad_regex(): void
    {
        $matcher = new RuleMatcher;

        $this->assertNull($matcher->validatePattern('ok\d+'));
        $this->assertNotNull($matcher->validatePattern('(aperta'));
        $this->assertNotNull($matcher->validatePattern(str_repeat('a', 501)));
    }

    public function test_body_is_cut_at_20000_chars(): void
    {
        $rule = $this->rule(['body_pattern' => 'FINE']);

        $this->assertFalse((new RuleMatcher)->matches($rule, '', '', str_repeat('x', 20000).'FINE'));
        $this->assertTrue((new RuleMatcher)->matches($rule, '', '', str_repeat('x', 19990).'FINE'));
    }
}
