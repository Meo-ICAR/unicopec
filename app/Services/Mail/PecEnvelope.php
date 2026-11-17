<?php

namespace App\Services\Mail;

use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

/**
 * Apre la busta di una PEC. Il gestore (Aruba, Legalmail...) è il mittente della busta:
 * mittente e oggetto veri stanno nell'allegato postacert.eml del messaggio certificato.
 */
class PecEnvelope
{
    /** Livelli di PEC inoltrate in cui si scende al massimo. */
    private const MAX_DEPTH = 4;

    /** Allegati tecnici della busta, non documenti del messaggio. */
    private const TECHNICAL = ['postacert.eml', 'daticert.xml', 'smime.p7s'];

    /** Valori dell'header X-Ricevuta → tipo di ricevuta. */
    private const RECEIPTS = [
        'accettazione' => 'accettazione',
        'non-accettazione' => 'non_accettazione',
        'presa-in-carico' => 'presa_in_carico',
        'avvenuta-consegna' => 'consegna',
        'errore-consegna' => 'errore_consegna',
        'preavviso-errore-consegna' => 'errore_consegna',
        'rilevazione-virus' => 'virus',
    ];

    /**
     * @return array{pec_type: ?string, from_email: ?string, from_name: ?string, subject: ?string, text: ?string, html: ?string, attachments: array<int, Attachment>}
     */
    public function open(Message $message, int $depth = 0): array
    {
        $receipt = strtolower(trim((string) $message->getHeader()?->get('x-ricevuta')));
        $transport = strtolower(trim((string) $message->getHeader()?->get('x-trasporto')));

        if ($receipt !== '') {
            return $this->result(self::RECEIPTS[$receipt] ?? 'ricevuta', $message, $message->getAttachments()->all());
        }

        // Messaggio certificato: il contenuto vero è dentro postacert.eml.
        if ($transport === 'posta-certificata' || $this->findPostacert($message)) {
            $inner = $this->innerMessage($message);

            if ($inner) {
                return $this->withForwarded($this->result('messaggio', $inner, $inner->getAttachments()->all(), $inner), $inner, $depth);
            }
        }

        // Non è una busta PEC (o non è apribile): si tratta come una email normale.
        return $this->result(null, $message, $message->getAttachments()->all());
    }

    private function result(?string $type, Message $source, array $attachments, ?Message $inner = null): array
    {
        $from = $source->getFrom()[0] ?? null;

        return [
            'pec_type' => $type,
            'from_email' => $from->mail ?? null,
            'from_name' => ($from->personal ?? '') ?: null,
            'subject' => $this->decode((string) $source->getSubject()),
            'text' => $this->utf8($source->getTextBody()),
            'html' => $this->utf8($source->getHTMLBody()),
            'attachments' => array_values(array_filter(
                $attachments,
                fn (Attachment $a) => ! in_array(strtolower((string) $a->getName()), self::TECHNICAL, true),
            )),
        ];
    }

    /**
     * PEC inoltrata come allegato (email.eml, a sua volta una busta PEC con il suo postacert.eml): il testo dell'inoltro è solo una riga,
     * il corpo che interessa è nel messaggio originale. Si scende nei livelli e lo si accoda, con mittente e oggetto veri.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withForwarded(array $data, Message $message, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return $data;
        }

        foreach ($message->getAttachments() as $attachment) {
            $name = strtolower((string) $attachment->getName());

            if (in_array($name, self::TECHNICAL, true) || ! (str_ends_with($name, '.eml') || str_contains(strtolower((string) $attachment->getMimeType()), 'rfc822'))) {
                continue;
            }

            try {
                $original = $this->open(Message::fromString($attachment->getContent()), $depth + 1);
            } catch (\Throwable) {
                continue;
            }

            if (blank($original['text']) && blank($original['html'])) {
                continue;
            }

            $header = 'Da: '.($original['from_email'] ?? '?')."\nOggetto: ".($original['subject'] ?? '');
            $text = $original['text'] ?? trim(html_entity_decode(strip_tags((string) preg_replace('~<(style|script)\b.*?</\1>|<br\s*/?>|</p>~is', "\n", (string) $original['html']))));

            $data['text'] = trim(($data['text'] ?? '')."\n\n----- Messaggio inoltrato -----\n{$header}\n\n{$text}");
            $data['html'] = ($data['html'] ?? '').'<hr><p><strong>Messaggio inoltrato</strong><br>Da: '.e($original['from_email'] ?? '?').'<br>Oggetto: '.e($original['subject'] ?? '').'</p>'
                .($original['html'] ?? '<pre style="white-space:pre-wrap">'.e($original['text']).'</pre>');
        }

        return $data;
    }

    private function findPostacert(Message $message): ?Attachment
    {
        foreach ($message->getAttachments() as $attachment) {
            if (strtolower((string) $attachment->getName()) === 'postacert.eml') {
                return $attachment;
            }
        }

        return null;
    }

    private function innerMessage(Message $message): ?Message
    {
        $postacert = $this->findPostacert($message);

        if (! $postacert) {
            return null;
        }

        try {
            return Message::fromString($postacert->getContent());
        } catch (\Throwable) {
            return null;
        }
    }

    /** Il corpo del messaggio interno può non essere UTF-8 (es. ISO-8859-1): MySQL lo rifiuterebbe. */
    private function utf8(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return $text;
    }

    private function decode(string $subject): string
    {
        return trim(@iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $subject);
    }
}
