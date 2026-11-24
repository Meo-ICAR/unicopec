<?php

namespace App\Services\Mail;

/**
 * Ricava dal testo di una email solo l'ultimo messaggio, senza la catena di risposte e inoltri citati sotto.
 * Salta l'intestazione dell'inoltro in testa («From:… Sent:…», «----- Messaggio inoltrato -----», «Da: … Oggetto: …»)
 * e si ferma alla prima intestazione di un messaggio precedente, a un separatore o a una citazione.
 */
class ReplyTrimmer
{
    private const HEADER_KEY = '/^\s*(from|da|sent|inviato|date|data|to|a|cc|ccn|bcc|subject|oggetto)\s*:/iu';

    /** Intestazione che apre un messaggio citato (Da:/From:), seguita entro pochi righi da altre chiavi. */
    private const QUOTE_START = '/^\s*(from|da)\s?:/iu';

    private const QUOTE_KEYS = '/^\s*(inviato|sent|date|data|a|to|oggetto|subject|cc)\s?:/iu';

    private const SEPARATORS = [
        '/^\s*-{2,}\s*(original message|messaggio originale|messaggio inoltrato|forwarded message)\s*-{2,}\s*$/iu',
        '/^\s*_{5,}\s*$/u',
        '/^\s*(il|on)\s.{5,120}\s(ha scritto|wrote)\s*:?\s*$/iu',
        '/^\s*>/u',
        '/^\s*Da\s+"Per conto di/iu',
    ];

    public static function latest(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $count = count($lines);
        $i = 0;

        // Intestazione dell'inoltro in testa: righe vuote, chiavi di intestazione, indicatore di inoltro.
        while ($i < $count) {
            $line = $lines[$i];
            if (trim($line) === '' || preg_match(self::HEADER_KEY, $line) || self::isSeparator($line)) {
                $i++;

                continue;
            }
            break;
        }

        $out = [];
        for ($j = $i; $j < $count; $j++) {
            if (self::startsQuote($lines, $j)) {
                break;
            }
            $out[] = $lines[$j];
        }

        $result = trim(implode("\n", $out));

        // Se non resta nulla di sensato, meglio il testo intero che un corpo vuoto.
        return mb_strlen($result) >= 2 ? $result : $text;
    }

    private static function isSeparator(string $line): bool
    {
        foreach (self::SEPARATORS as $re) {
            if (preg_match($re, $line)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $lines */
    private static function startsQuote(array $lines, int $at): bool
    {
        if (self::isSeparator($lines[$at])) {
            return true;
        }

        if (! preg_match(self::QUOTE_START, $lines[$at])) {
            return false;
        }

        // Una riga «Da:» isolata può essere testo normale: è una citazione solo se segue un'altra chiave di intestazione.
        for ($k = $at + 1; $k <= $at + 4 && $k < count($lines); $k++) {
            if (preg_match(self::QUOTE_KEYS, $lines[$k])) {
                return true;
            }
        }

        return false;
    }
}
