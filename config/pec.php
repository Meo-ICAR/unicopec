<?php

return [
    /*
     * Organigramma di ripiego. Le società che non hanno ancora dipendenti censiti (es. HASSISTO) usano, per smistare
     * le PEC, i ruoli e i referenti di questa società: in simulazione è RACES (company_id 1).
     */
    'org_fallback_company_id' => (int) env('PEC_ORG_FALLBACK_COMPANY_ID', 1),

    'forward' => [
        // Se l'organigramma è quello di ripiego (simulazione) gli inoltri non vanno ai referenti veri ma a questo indirizzo di prova.
        // Vuoto = l'inoltro viene solo registrato come «simulato».
        'simulation_to' => env('PEC_FORWARD_SIMULATION_TO', ''),
    ],

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        // Caratteri del corpo email inviati all'AI: il resto è tagliato.
        'body_chars' => 4000,
    ],
];
