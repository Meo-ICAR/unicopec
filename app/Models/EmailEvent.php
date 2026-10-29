<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voce del registro di un'email: cosa è successo, quando e con quale esito. */
class EmailEvent extends Model
{
    public const CLASSIFIED = 'classified';

    public const ASSIGNED = 'assigned';

    public const BPM_EVENT = 'bpm_event';

    public const FORWARDED = 'forwarded';

    public const ANOMALY = 'anomaly';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(IncomingEmail::class, 'incoming_email_id');
    }

    /** @param array<string, mixed>|null $meta */
    public static function record(IncomingEmail $email, string $kind, string $status, string $message, ?array $meta = null): self
    {
        return static::create(['incoming_email_id' => $email->id, 'kind' => $kind, 'status' => $status, 'message' => mb_substr($message, 0, 500), 'meta' => $meta]);
    }
}
