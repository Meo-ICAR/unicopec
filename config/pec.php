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

    // Fuso orario che decide l'anno del protocollo (la numerazione riparte a Capodanno italiano, non UTC).
    'protocol_timezone' => env('PEC_PROTOCOL_TIMEZONE', 'Europe/Rome'),

    'archive' => [
        // Servizio di archiviazione a norma: per ora esiste solo `fake`, che non invia nulla.
        'driver' => env('PEC_ARCHIVE_DRIVER', 'fake'),
        // Giorni di attesa della presa in carico, poi si archivia con il solo protocollo di smistamento.
        'max_wait_days' => (int) env('PEC_ARCHIVE_MAX_WAIT_DAYS', 7),
    ],

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        // Caratteri del corpo email inviati all'AI: il resto è tagliato.
        'body_chars' => 4000,
    ],
];
