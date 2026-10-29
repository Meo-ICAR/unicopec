<?php

namespace App\Models;

use App\Services\Mail\EmailAnomalies;
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
            'taken_at' => 'datetime',
            'awaiting_since' => 'datetime',
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
        return EmailAnomalies::for($this);
    }

    /** @return array{label: string, color: string} */
    public function outcome(): array
    {
        return EmailAnomalies::outcome($this);
    }

    public function scopeWithAnomalies($query)
    {
        return EmailAnomalies::scope($query);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'taken_by_id');
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
