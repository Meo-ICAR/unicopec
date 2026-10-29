<?php

/*
 * Comandi che UnicoBPM può lanciare via API (POST /api/commands/{comando}). Solo quelli elencati e solo con le opzioni indicate.
 * L'esecuzione è in coda e ha un esito leggibile (GET /api/commands/runs/{id}).
 */
return [
    'mail:fetch' => [
        'description' => 'Legge le caselle attive e importa le nuove email/PEC (apre la busta PEC).',
        'options' => ['days', 'limit'],
    ],
    'mail:classify' => [
        'description' => 'Classifica le email nuove (regole certe, poi AI) e comunica l\'evento email.ricevuta.',
        'options' => ['limit'],
    ],
    'mail:route' => [
        'description' => 'Assegna le email classificate al referente dell\'organigramma e comunica l\'evento email.assegnata.',
        'options' => ['limit'],
    ],
];
