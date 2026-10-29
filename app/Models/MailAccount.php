<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Casella email o PEC: IMAP in ingresso, SMTP in uscita. */
class MailAccount extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['imap_password', 'smtp_password', 'access_token', 'refresh_token'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'imap_port' => 'integer',
            'imap_password' => 'encrypted',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'is_pec' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
