<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use DutchCodingCompany\FilamentSocialite\Models\SocialiteUser;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function socialiteUsers(): HasMany
    {
        return $this->hasMany(SocialiteUser::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Admin e super admin possono modificare ed eliminare le email ricevute; gli altri solo consultarle e lavorarle (classifica, smista, inoltra). */
    public function canManageEmails(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    /** Solo il super admin vede tutte le società; chiunque altro (anche un admin) vede solo quella di `users.company_id`. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** @return Collection<int, Company> */
    public function getTenants(Panel $panel): Collection
    {
        return $this->isSuperAdmin() ? Company::query()->orderBy('name')->get() : Company::query()->whereKey($this->company_id)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->isSuperAdmin() || $this->company_id !== null && (int) $this->company_id === (int) $tenant->getKey();
    }
}
