<?php

namespace App\Services\Mail;

use App\Models\ClassificationRule;
use App\Models\IncomingEmail;
use Illuminate\Support\Facades\Log;

/** Applica le regole di classificazione a un'email. Un pattern non valido non corrisponde mai e non interrompe la classificazione. */
class RuleMatcher
{
    public const MAX_PATTERN = 500;

    public const MAX_BODY = 20000;

    public function firstMatch(IncomingEmail $email): ?ClassificationRule
    {
        $body = mb_substr(strip_tags((string) ($email->body_text ?: $email->body_html)), 0, self::MAX_BODY);

        foreach (ClassificationRule::matchesFor($email->company_id)->get() as $rule) {
            if ($this->matches($rule, (string) $email->from_email, (string) $email->subject, $body)) {
                return $rule;
            }
        }

        return null;
    }

    public function matches(ClassificationRule $rule, string $from, string $subject, string $body): bool
    {
        $results = [];

        foreach ([[$rule->sender_pattern, $from], [$rule->subject_pattern, $subject], [$rule->body_pattern, mb_substr($body, 0, self::MAX_BODY)]] as [$pattern, $text]) {
            if ($pattern !== null && $pattern !== '') {
                $results[] = $this->test((string) $pattern, $text);
            }
        }

        if ($results === []) {
            return false;
        }

        return $rule->operator === 'and' ? ! in_array(false, $results, true) : in_array(true, $results, true);
    }

    /** @return string|null il messaggio di errore, o null se il pattern è valido */
    public function validatePattern(string $pattern): ?string
    {
        if (mb_strlen($pattern) > self::MAX_PATTERN) {
            return 'Il pattern supera '.self::MAX_PATTERN.' caratteri.';
        }

        return @preg_match($this->delimited($pattern), '') === false ? 'Espressione regolare non valida.' : null;
    }

    private function test(string $pattern, string $text): bool
    {
        if (mb_strlen($pattern) > self::MAX_PATTERN) {
            return false;
        }

        $result = @preg_match($this->delimited($pattern), $text);

        if ($result === false) {
            Log::warning('Regola di classificazione: errore del motore regex', ['pattern' => mb_substr($pattern, 0, 80), 'error' => preg_last_error_msg()]);

            return false;
        }

        return $result === 1;
    }

    private function delimited(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~iu';
    }
}
