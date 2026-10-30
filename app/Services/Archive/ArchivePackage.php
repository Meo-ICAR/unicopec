<?php

namespace App\Services\Archive;

use App\Models\IncomingEmail;

/** Ciò che si consegna al servizio: i file con la loro impronta SHA-256, i metadati con i protocolli e se è un'integrazione di un invio precedente. */
readonly class ArchivePackage
{
    /**
     * @param  array<int, array{name: string, sha256: string, path: string}>  $files
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public int $emailId, public array $files, public array $metadata, public bool $supplement) {}

    /** Collection dei media con l'`.eml` originale, quando c'è. */
    public const RAW = 'email_raw';

    public static function for(IncomingEmail $email, bool $supplement = false): self
    {
        $files = [];
        $missing = [];

        foreach ([...$email->getMedia(self::RAW), ...$email->getMedia(IncomingEmail::ATTACHMENTS)] as $media) {
            $path = $media->getPath();

            if (! is_file($path)) {
                $missing[] = $media->file_name;

                continue;
            }

            $files[] = ['name' => $media->file_name, 'sha256' => hash_file('sha256', $path), 'path' => $path];
        }

        $email->loadMissing('protocols.register', 'protocols.employee');

        return new self($email->id, $files, [
            'email_id' => $email->id,
            'company_id' => $email->company_id,
            'message_id' => $email->message_id,
            'from' => $email->from_email,
            'subject' => $email->subject,
            'received_at' => $email->received_at?->toIso8601String(),
            'eml_available' => $email->getMedia(self::RAW)->isNotEmpty(),
            'missing_files' => $missing,
            'protocols' => $email->protocols->map(fn ($p) => [
                'identifier' => $p->identifier,
                'type' => $p->type,
                'register' => $p->register?->code,
                'role' => $p->role,
                'employee' => $p->employee?->name,
                'protocolled_at' => $p->protocolled_at?->toIso8601String(),
            ])->all(),
        ], $supplement);
    }
}
