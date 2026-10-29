<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomingEmail;
use Illuminate\Http\JsonResponse;

class IncomingEmailApiController extends Controller
{
    /** Dettaglio di un'email per i processi di UnicoBPM: intestazioni, classificazione, referente e allegati (solo nomi). */
    public function show(int $id): JsonResponse
    {
        $email = IncomingEmail::with('mailAccount:id,email_address,is_pec')->findOrFail($id);

        return response()->json([
            'id' => $email->id,
            'company_id' => $email->company_id,
            'mail_account' => $email->mailAccount?->email_address,
            'canale' => $email->mailAccount?->is_pec ? 'pec' : 'email',
            'message_id' => $email->message_id,
            'thread_id' => $email->thread_id,
            'from_email' => $email->from_email,
            'from_name' => $email->from_name,
            'to' => $email->to,
            'subject' => $email->subject,
            'body_text' => $email->body_text,
            'received_at' => $email->received_at?->toIso8601String(),
            'classification' => $email->classification,
            'related_type' => $email->related_type,
            'related_id' => $email->related_id,
            'attachments' => $email->getMedia(IncomingEmail::ATTACHMENTS)->map(fn ($m) => [
                'name' => $m->file_name, 'mime' => $m->mime_type, 'size' => $m->size,
            ])->values(),
        ]);
    }
}
