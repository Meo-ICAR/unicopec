<?php

namespace App\Services\Mail;

use App\Enums\EmailCategory;
use App\Models\EmailEvent;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Services\Bpm\BpmEvents;

/**
 * Cosa succede dopo ogni passo, in un posto solo (comandi, coda e schermate lo usano allo stesso modo): si scrive nel registro e si avvisa UnicoBPM.
 * Se UnicoBPM non è configurato la PEC si inoltra subito al referente; se lo è, decide l'esito dell'evento `email.assegnata`
 * (nessun processo avviato = inoltro, vedi SendBpmEvent).
 */
class EmailPipeline
{
    public function __construct(private EmailForwarder $forwarder) {}

    public function classified(IncomingEmail $email, Classification $result): void
    {
        $email->forceFill(['classification' => $result->category->value])->save();

        EmailEvent::record($email, EmailEvent::CLASSIFIED, 'ok', sprintf('%s · urgenza %s · %s', $result->category->getLabel(), $result->urgency, $result->source === 'rules' ? 'regole' : 'AI '.number_format($result->confidence, 2)), [
            'category' => $result->category->value, 'urgency' => $result->urgency, 'summary' => $result->summary, 'source' => $result->source,
        ]);

        BpmEvents::dispatchReceived($email, $result);
    }

    /** @param array{employee: Employee, role: string, via: string, org_company_id: int} $found */
    public function routed(IncomingEmail $email, array $found): void
    {
        $email->forceFill(['related_type' => 'employee', 'related_id' => $found['employee']->id])->save();

        EmailEvent::record($email, EmailEvent::ASSIGNED, 'ok', "Assegnata a {$found['employee']->name} ({$found['role']}; {$found['via']})", [
            'employee_id' => $found['employee']->id, 'role' => $found['role'], 'via' => $found['via'], 'org_company_id' => $found['org_company_id'],
        ]);

        if (! BpmEvents::isConfigured()) {
            $this->forwarder->forward($email->refresh(), 'UnicoBPM non configurato');

            return;
        }

        BpmEvents::dispatchAssigned($email, $found['employee'], $found['role']);
    }

    public function noReferent(IncomingEmail $email, EmailCategory $category): void
    {
        EmailEvent::record($email, EmailEvent::ANOMALY, 'error', "Nessun referente trovato per la categoria «{$category->getLabel()}»");
    }
}
