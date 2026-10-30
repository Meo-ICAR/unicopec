<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;
use App\Models\ClassificationRule;
use App\Models\IncomingEmail;
use Illuminate\Support\Collection;

/** «Prova la regola»: applica una regola (anche non salvata) alle email già ricevute, senza modificare niente. */
class RuleTester
{
    /** Quante email recenti si esaminano al massimo. */
    private const SCAN = 500;

    public function __construct(private RuleMatcher $matcher) {}

    /** @return Collection<int, IncomingEmail> le più recenti che corrispondono alla regola */
    public function sample(ClassificationRule $rule, int $limit = 20): Collection
    {
        $found = new Collection;

        $emails = IncomingEmail::query()
            ->when($rule->company_id, fn ($q, $id) => $q->where('company_id', $id))
            ->where(fn ($q) => $q->whereNull('classification')->orWhere('classification', '!=', EmailCategory::ProviderNotification->value))
            ->latest('received_at')->limit(self::SCAN)->get();

        foreach ($emails as $email) {
            $body = mb_substr(strip_tags((string) ($email->body_text ?: $email->body_html)), 0, RuleMatcher::MAX_BODY);

            if ($this->matcher->matches($rule, (string) $email->from_email, (string) $email->subject, $body)) {
                $found->push($email);

                if ($found->count() >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }
}
