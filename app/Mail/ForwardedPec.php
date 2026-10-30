<?php

namespace App\Mail;

use App\Enums\EmailCategory;
use App\Models\Employee;
use App\Models\IncomingEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Inoltro di una PEC/email al referente. Il corpo è testo semplice (mai HTML di terzi); gli allegati si accodano entro un limite di dimensione. */
class ForwardedPec extends Mailable
{
    private const MAX_ATTACHMENTS_BYTES = 15 * 1024 * 1024;

    public function __construct(public IncomingEmail $email, public Employee $employee, public ?string $banner = null, public ?string $role = null, public ?string $takeUrl = null, public ?string $note = null, public ?string $subjectOverride = null, public ?string $htmlBody = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectOverride ?: 'Inoltro '.($this->email->mailAccount?->is_pec ? 'PEC' : 'email').': '.$this->email->subject,
            replyTo: $this->email->from_email ? [new Address($this->email->from_email, (string) $this->email->from_name)] : [],
        );
    }

    public function content(): Content
    {
        if ($this->htmlBody !== null) {
            return new Content(htmlString: $this->htmlBody);
        }

        $media = $this->email->getMedia(IncomingEmail::ATTACHMENTS);
        $tooBig = $media->sum('size') > self::MAX_ATTACHMENTS_BYTES;

        return new Content(view: 'emails.forwarded-pec', with: [
            'email' => $this->email,
            'banner' => $this->banner,
            'role' => $this->role,
            'takeUrl' => $this->takeUrl,
            'note' => $this->note,
            'category' => EmailCategory::tryFrom((string) $this->email->classification)?->getLabel(),
            'bodyText' => trim((string) ($this->email->body_text ?: strip_tags((string) $this->email->body_html))),
            'attachmentsNote' => $media->isEmpty() ? 'nessuno' : ($tooBig
                ? $media->count().' (troppo grandi per l\'inoltro, restano in UnicoPEC: '.$media->pluck('file_name')->implode(', ').')'
                : $media->pluck('file_name')->implode(', ')),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $media = $this->email->getMedia(IncomingEmail::ATTACHMENTS);

        if ($media->sum('size') > self::MAX_ATTACHMENTS_BYTES) {
            return [];
        }

        return $media->map(fn ($m) => Attachment::fromPath($m->getPath())->as($m->file_name)->withMime($m->mime_type ?: 'application/octet-stream'))->all();
    }
}
