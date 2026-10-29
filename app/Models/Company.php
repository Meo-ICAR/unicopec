<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['imap_password', 'pec_imap_password', 'db_password', 'preventivatore_passkey', 'istruttoria_passkey'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_trial' => 'boolean',
            'imap_password' => 'encrypted',
            'pec_imap_password' => 'encrypted',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function incomingEmails(): HasMany
    {
        return $this->hasMany(IncomingEmail::class);
    }

    public function mailAccounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
