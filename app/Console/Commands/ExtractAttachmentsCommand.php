<?php

namespace App\Console\Commands;

use App\Models\IncomingEmail;
use App\Services\Mail\AttachmentTextExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('mail:extract-attachments {--email= : solo gli allegati di questa email} {--media= : solo questo allegato} {--missing : solo gli allegati non ancora elaborati} {--limit=200 : numero massimo di allegati} {--show : mostra l\'inizio del testo estratto}')]
#[Description('Estrae il testo dagli allegati delle email (PDF, Word, Excel, immagini, p7m) e lo salva tra le proprietà del file')]
class ExtractAttachmentsCommand extends Command
{
    public function handle(AttachmentTextExtractor $extractor): int
    {
        $query = Media::query()
            ->where('model_type', (new IncomingEmail)->getMorphClass())
            ->where('collection_name', IncomingEmail::ATTACHMENTS)
            ->orderBy('id');

        if ($this->option('email')) {
            $query->where('model_id', (int) $this->option('email'));
        }

        if ($this->option('media')) {
            $query->whereKey((int) $this->option('media'));
        }

        $done = 0;

        foreach ($query->limit((int) $this->option('limit'))->get() as $media) {
            if ($this->option('missing') && $media->getCustomProperty('extracted_at')) {
                continue;
            }

            $extractor->extractMedia($media->fresh());
            $media = $media->fresh();
            $done++;

            $this->line(sprintf(
                '#%d %s - %s%s (%d caratteri)',
                $media->id,
                $media->file_name,
                $media->getCustomProperty('extracted_method', '-'),
                $media->getCustomProperty('extracted_error') ? ' - ERRORE: '.$media->getCustomProperty('extracted_error') : '',
                mb_strlen((string) $media->getCustomProperty('extracted_text', '')),
            ));

            if ($this->option('show')) {
                $this->line(mb_substr((string) $media->getCustomProperty('extracted_text', ''), 0, 1500));
                $this->newLine();
            }
        }

        $this->info("Allegati elaborati: {$done}");

        return self::SUCCESS;
    }
}
