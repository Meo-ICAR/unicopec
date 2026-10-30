<?php

namespace App\Services\Protocol;

use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use Illuminate\Support\Facades\DB;

/** Assegna il numero di protocollo: progressivo per registro e anno, in transazione con blocco sul contatore. */
class ProtocolNumberer
{
    public function assign(ProtocolRegister $register, IncomingEmail $email, string $type, ?string $role, ?Employee $employee, ?Protocol $parent = null, ?int $userId = null): Protocol
    {
        return DB::transaction(function () use ($register, $email, $type, $role, $employee, $parent, $userId) {
            $now = now();
            $year = $now->year;

            DB::table('protocol_register_counters')->insertOrIgnore(['protocol_register_id' => $register->id, 'year' => $year, 'last_number' => 0]);

            $last = (int) DB::table('protocol_register_counters')->where('protocol_register_id', $register->id)->where('year', $year)->lockForUpdate()->value('last_number');
            $number = $last + 1;

            // Il protocollo si crea prima di spostare il contatore: se il vincolo (una mail, un tipo) scatta, il numero non si consuma.
            $protocol = Protocol::create([
                'protocol_register_id' => $register->id,
                'year' => $year,
                'number' => $number,
                'identifier' => sprintf('%s/%d/%06d', $register->code, $year, $number),
                'incoming_email_id' => $email->id,
                'type' => $type,
                'role' => $role,
                'employee_id' => $employee?->id,
                'parent_protocol_id' => $parent?->id,
                'created_by' => $userId,
                'protocolled_at' => $now,
            ]);

            DB::table('protocol_register_counters')->where('protocol_register_id', $register->id)->where('year', $year)->update(['last_number' => $number]);

            return $protocol;
        });
    }
}
