<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;
use App\Mail\ForwardedPec;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\MailAccount;
use Illuminate\Support\Facades\Mail;
use Unico\Core\Mail\EmailTemplateResolver;
use Unico\Core\Mail\OutgoingMailer;
use Unico\Core\Models\MailAccount as CoreMailAccount;

/**
 * Costruisce e spedisce l'inoltro al referente con unico-core: il testo viene dal modello `pec.inoltro` di email_templates (della società, o comune)
 * se esiste, altrimenti dal testo predefinito; l'invio parte dalla casella SMTP della società, e in mancanza dal mailer dell'app.
 *
 * Segnaposto del modello: {oggetto} {mittente} {categoria} {ruolo} {destinatario} {protocollo} {link_presa_in_carico} {nota} {banner} {testo}.
 */
class ForwardMessageFactory
{
    public const TEMPLATE = 'pec.inoltro';

    public const APP = 'unicopec';

    public function __construct(private EmailTemplateResolver $templates, private OutgoingMailer $mailer) {}

    public function make(IncomingEmail $email, Employee $employee, ?string $banner = null, ?string $role = null, ?string $takeUrl = null, ?string $note = null): ForwardedPec
    {
        $template = $this->templates->resolve(self::TEMPLATE, $email->company_id, self::APP);

        if (! $template || blank($template->body)) {
            return new ForwardedPec($email, $employee, $banner, $role, $takeUrl, $note);
        }

        $values = [
            '{oggetto}' => (string) $email->subject,
            '{mittente}' => (string) ($email->from_email ?: $email->from_name),
            '{categoria}' => (string) EmailCategory::tryFrom((string) $email->classification)?->getLabel(),
            '{ruolo}' => (string) $role,
            '{destinatario}' => (string) $employee->name,
            '{protocollo}' => $email->protocols()->pluck('identifier')->implode(', '),
            '{link_presa_in_carico}' => (string) $takeUrl,
            '{nota}' => (string) $note,
            '{banner}' => (string) $banner,
            '{testo}' => nl2br(e(trim((string) ($email->body_text ?: strip_tags((string) $email->body_html))))),
        ];

        // Nel corpo HTML ogni valore è testo di terzi e si scrive con l'escape (tranne {testo}, già trattato); l'oggetto è testo semplice.
        $html = strtr((string) $template->body, collect($values)->map(fn ($v, $k) => $k === '{testo}' ? $v : e($v))->all());
        $subject = strtr((string) $template->subject, collect($values)->except('{testo}')->all());

        return new ForwardedPec($email, $employee, $banner, $role, $takeUrl, $note, trim($subject) ?: null, $html);
    }

    public function deliver(IncomingEmail $email, string $to, ForwardedPec $mail): void
    {
        $account = $this->account($email);

        if (! $account) {
            Mail::to($to)->send($mail);

            return;
        }

        $this->mailer->send($account, $to, $mail);
    }

    /** Casella SMTP della società, in forma di unico-core: le credenziali restano cifrate e si leggono con la chiave dell'app. */
    private function account(IncomingEmail $email): ?CoreMailAccount
    {
        if ($email->company_id === null) {
            return null;
        }

        $own = MailAccount::query()->where('company_id', $email->company_id)->where('is_active', true)->orderBy('is_pec')->orderBy('id')->get()
            ->first(fn (MailAccount $a) => ($a->auth_type ?? 'password') === 'password' && filled($a->smtp_host) && filled($a->smtp_password));

        if (! $own) {
            return null;
        }

        $account = new CoreMailAccount;
        $account->setRawAttributes($own->getAttributes(), true);

        return $account;
    }
}
