<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Email o PEC scaricata da una casella. Gli allegati stanno nella collection `email_attachments`. */
class IncomingEmail extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    public const ATTACHMENTS = 'email_attachments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'received_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class)->orderBy('id');
    }

    /** @return array<int, string> */
    public function anomalies(): array
    {
        return \App\Services\Mail\EmailAnomalies::for($this);
    }

    /** @return array{label: string, color: string} */
    public function outcome(): array
    {
        return \App\Services\Mail\EmailAnomalies::outcome($this);
    }

    public function scopeWithAnomalies($query)
    {
        return \App\Services\Mail\EmailAnomalies::scope($query);
    }

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    /** Record a cui è stata smistata l'email (il referente: dipendente o consulente). */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
