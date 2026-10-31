<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\RAG\DataLoader\ReaderInterface;

/**
 * Estrae il solo testo leggibile da un file HTML, per l'indicizzazione RAG.
 *
 * Il pacchetto NeuronAI include un HtmlReader basato su html2text/html2text,
 * ma quella libreria è solo una dev-dependency del pacchetto neuron-ai e non
 * è installata in questo progetto. Piuttosto che aggiungere una dipendenza
 * in più per un compito semplice, usiamo le funzioni native di PHP.
 */
class PlainHtmlReader implements ReaderInterface
{
    public static function getText(string $filePath, array $options = []): string
    {
        $html = (string) file_get_contents($filePath);

        // Rimuove interamente stili e script: non contengono testo utile.
        $html = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        // Inserisce interruzioni di riga dove ci sono tag di blocco/heading,
        // cosi' il testo estratto resta leggibile e ben suddiviso in blocchi.
        $html = preg_replace('/<\/(p|div|li|tr|h[1-6]|br)\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<(br)\s*\/?>/i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Comprime spazi/righe multiple lasciate dai tag rimossi.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
