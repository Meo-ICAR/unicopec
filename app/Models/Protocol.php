<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Numero di protocollo assegnato a un'email. Una volta creato non si modifica né si cancella. */
class Protocol extends Model
{
    public const SMISTAMENTO = 'smistamento';

    public const PRESA_IN_CARICO = 'presa_in_carico';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['protocolled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un protocollo non si modifica.'));
        static::deleting(fn () => throw new LogicException('Un protocollo non si cancella.'));
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(ProtocolRegister::class, 'protocol_register_id');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(IncomingEmail::class, 'incoming_email_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_protocol_id');
    }
}
