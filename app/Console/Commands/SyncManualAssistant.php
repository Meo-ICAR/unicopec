<?php

namespace App\Console\Commands;

use App\Neuron\ManualAssistantAgent;
use App\Neuron\PlainHtmlReader;
use Illuminate\Console\Command;
use NeuronAI\RAG\DataLoader\FileDataLoader;

/**
 * Indicizza la documentazione di progetto nel vector store usato da
 * ManualAssistantAgent. Da rilanciare ogni volta che i documenti cambiano.
 */
class SyncManualAssistant extends Command
{
    protected $signature = 'manual:sync';

    protected $description = 'Indicizza la documentazione dell\'assistente AI nel vector store';

    public function handle(): int
    {
        $storeFile = storage_path('app/neuron/manual/manual.store');

        if (file_exists($storeFile)) {
            unlink($storeFile);
        }

        $agent = ManualAssistantAgent::make();
        $totalChunks = 0;

        foreach (ManualAssistantAgent::manualSources() as $path) {
            if (! file_exists($path)) {
                $this->warn("File non trovato, saltato: {$path}");

                continue;
            }

            // Registriamo un reader per i manuali .html: senza reader dedicato
            // FileDataLoader indicizzerebbe l'HTML grezzo (tag compresi) invece
            // del solo testo.
            $documents = FileDataLoader::for($path)
                ->addReader('html', new PlainHtmlReader)
                ->getDocuments();
            $agent->addDocuments($documents);
            $totalChunks += count($documents);

            $this->info(basename($path).': '.count($documents).' blocchi indicizzati.');
        }

        $this->info("Totale: {$totalChunks} blocchi indicizzati.");

        return self::SUCCESS;
    }
}
