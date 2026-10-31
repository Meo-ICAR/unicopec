<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssistantEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public User $user,
        public string $prompt,
        public ?string $answer,
        public ?string $error,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Assistente AI UnicoPEC] Escalation {$this->code}", replyTo: [$this->user->email]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.assistant-escalation');
    }
}
