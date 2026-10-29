<?php

return [
    /*
     * Organigramma di ripiego. Le società che non hanno ancora dipendenti censiti (es. HASSISTO) usano, per smistare
     * le PEC, i ruoli e i referenti di questa società: in simulazione è RACES (company_id 1).
     */
    'org_fallback_company_id' => (int) env('PEC_ORG_FALLBACK_COMPANY_ID', 1),

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        // Caratteri del corpo email inviati all'AI: il resto è tagliato.
        'body_chars' => 4000,
    ],
];
