<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Services\Mail\EmailHandover;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Presa in carico dal link firmato nella PEC inoltrata. Il GET mostra solo la conferma (gli antivirus della posta aprono i link:
 * non devono prendere in carico al posto della persona); la presa in carico avviene con il POST.
 */
class TakeEmailController extends Controller
{
    public function show(IncomingEmail $email, Employee $employee): View
    {
        return view('pec.take', ['email' => $email, 'employee' => $employee, 'result' => null]);
    }

    public function store(Request $request, IncomingEmail $email, Employee $employee, EmailHandover $handover): View
    {
        return view('pec.take', ['email' => $email, 'employee' => $employee, 'result' => $handover->take($email, $employee)]);
    }
}
