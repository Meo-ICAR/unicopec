<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cliente o consulente (persona o società) anagrafato per una società. */
class Client extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'employee_roles' => 'array',
            'is_person' => 'boolean',
            'is_company' => 'boolean',
            'is_client' => 'boolean',
            'is_lead' => 'boolean',
            'is_consultant_gdpr' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
