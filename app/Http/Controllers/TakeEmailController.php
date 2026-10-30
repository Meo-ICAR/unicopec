<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Services\Mail\EmailHandover;
use App\Services\Protocol\PresaInCaricoProtocoller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Presa in carico dal link firmato nella PEC inoltrata. Il GET mostra solo la conferma (gli antivirus della posta aprono i link:
 * non devono prendere in carico al posto della persona); la presa in carico avviene con il POST.
 */
class TakeEmailController extends Controller
{
    public function show(IncomingEmail $email, Employee $employee, PresaInCaricoProtocoller $protocols): View
    {
        return view('pec.take', $this->data($email, $employee, $protocols, null));
    }

    public function store(Request $request, IncomingEmail $email, Employee $employee, EmailHandover $handover, PresaInCaricoProtocoller $protocols): View
    {
        $registerId = $request->integer('register_id') ?: null;

        return view('pec.take', $this->data($email, $employee, $protocols, $handover->take($email, $employee, 'link', $registerId), $registerId));
    }

    /** @return array<string, mixed> */
    private function data(IncomingEmail $email, Employee $employee, PresaInCaricoProtocoller $protocols, ?array $result, ?int $chosen = null): array
    {
        $registers = $protocols->isInUse($email) ? $protocols->registersFor($email, $employee) : collect();

        return [
            'email' => $email, 'employee' => $employee, 'result' => $result,
            'registers' => $registers,
            'registersInUse' => $protocols->isInUse($email),
            'selected' => $chosen ?? ($registers->count() === 1 ? $registers->first()->id : null),
        ];
    }
}
