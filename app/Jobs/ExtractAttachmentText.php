<?php

namespace App\Jobs;

use App\Services\Mail\AttachmentTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Estrae il testo di un allegato email e lo salva tra le proprietà del file; non ripete l'estrazione già fatta, salvo richiesta. */
class ExtractAttachmentText implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public int $mediaId, public bool $force = false) {}

    public function handle(AttachmentTextExtractor $extractor): void
    {
        $media = Media::query()->find($this->mediaId);

        if (! $media || (! $this->force && $media->getCustomProperty('extracted_at'))) {
            return;
        }

        $extractor->extractMedia($media);
    }
}
