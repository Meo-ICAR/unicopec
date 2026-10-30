<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Registro di protocollo di una società, collegato a una o più funzioni aziendali (nomi di employee_types). */
class ProtocolRegister extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Il numero leggibile contiene la sigla: dopo il primo protocollo non può cambiare.
        static::updating(function (self $register) {
            if ($register->isDirty('code') && $register->protocols()->exists()) {
                throw new \LogicException('La sigla di un registro con protocolli non si cambia.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    /** @return array<int, string> */
    public function roleNames(): array
    {
        return DB::table('protocol_register_roles')->where('protocol_register_id', $this->getKey())->pluck('role')->all();
    }

    /** @param array<int, string> $roles */
    public function syncRoles(array $roles): void
    {
        DB::transaction(function () use ($roles) {
            DB::table('protocol_register_roles')->where('protocol_register_id', $this->getKey())->delete();
            DB::table('protocol_register_roles')->insert(collect($roles)->unique()->map(fn (string $role) => ['protocol_register_id' => $this->getKey(), 'role' => $role])->values()->all());
        });
    }

    /**
     * Registri attivi della società collegati ad almeno una delle funzioni, per sigla.
     *
     * @param  array<int, string>  $roles
     * @return Collection<int, self>
     */
    public static function forRoles(int $companyId, array $roles): Collection
    {
        if ($roles === []) {
            return new Collection;
        }

        return static::query()->where('company_id', $companyId)->where('is_active', true)
            ->whereIn('id', DB::table('protocol_register_roles')->whereIn('role', $roles)->select('protocol_register_id'))
            ->orderBy('code')->get();
    }
}
