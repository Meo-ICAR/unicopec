<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;

/** Esito della classificazione di un'email. `source` dice come è nata: `rules` (regole certe) o `ai`. */
readonly class Classification
{
    public function __construct(
        public EmailCategory $category,
        public string $urgency,
        public string $summary,
        public float $confidence,
        public string $source,
    ) {}
}
