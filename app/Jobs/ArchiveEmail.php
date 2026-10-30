<?php

namespace App\Jobs;

use App\Models\EmailEvent;
use App\Models\IncomingEmail;
use App\Services\Archive\ArchivePackage;
use App\Services\Archive\ArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Invia un'email protocollata al servizio di archiviazione. Si ripete con attesa crescente; dopo l'ultimo tentativo lo stato è «errore» e compare un'anomalia.
 * Se l'email è già archiviata ma ha più protocolli di quanti ne conteneva l'invio (la presa in carico è arrivata dopo), parte un'integrazione.
 */
class ArchiveEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $emailId) {}

    public function handle(ArchiveService $service): void
    {
        $email = IncomingEmail::query()->find($this->emailId);
        $count = $email?->protocols()->count() ?? 0;

        if (! $email || $count === 0 || ($email->archive_status === 'archiviata' && $email->archived_protocols >= $count)) {
            return;
        }

        $supplement = $email->archive_status === 'archiviata';
        $reference = $service->send(ArchivePackage::for($email, $supplement));

        $email->forceFill(['archive_status' => 'archiviata', 'archive_reference' => $reference, 'archived_at' => now(), 'archived_protocols' => $count])->save();

        EmailEvent::record($email, EmailEvent::ARCHIVED, 'ok', ($supplement ? 'Integrazione archiviata' : 'Archiviata')." ({$reference})", ['reference' => $reference, 'supplement' => $supplement, 'protocols' => $count]);
    }

    public function failed(Throwable $e): void
    {
        $email = IncomingEmail::query()->find($this->emailId);

        if (! $email) {
            return;
        }

        $email->forceFill(['archive_status' => 'errore'])->save();
        EmailEvent::record($email, EmailEvent::ANOMALY, 'error', 'Archiviazione non riuscita: '.mb_substr($e->getMessage(), 0, 200));
    }
}
