<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\IncomingEmail;
use App\Models\MailAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Espone le colonne di un modello (con i commenti reali di MySQL) a UnicoBPM, che non ha accesso diretto
 * alle tabelle di questa app e le usa per costruire le select di configurazione dei processi.
 */
class ModelFieldIntrospector
{
    /** @var array<string, class-string<Model>> */
    public const MODEL_MAP = [
        'email' => IncomingEmail::class,
        'casella' => MailAccount::class,
        'societa' => Company::class,
        'cliente' => Client::class,
        'employee' => Employee::class,
    ];

    /** Campi che UnicoBPM può scrivere: solo l'esito della gestione di un'email, mai anagrafiche o caselle. */
    private const WRITABLE = [
        'email' => ['classification', 'related_type', 'related_id', 'is_read'],
    ];

    /** @return array<int, string> */
    public function writable(string $modelType): array
    {
        return self::WRITABLE[$modelType] ?? [];
    }

    /** Colonne mai esposte: segreti e token. */
    private const HIDDEN = ['imap_password', 'smtp_password', 'access_token', 'refresh_token', 'pec_imap_password', 'db_password', 'preventivatore_passkey', 'istruttoria_passkey', 'password'];

    /** @return class-string<Model> */
    public function resolve(string $modelType): string
    {
        return self::MODEL_MAP[$modelType] ?? throw new InvalidArgumentException("Modello sconosciuto: {$modelType}");
    }

    /** @return array<int, array{name: string, type: string, comment: string|null, nullable: bool}> */
    public function columns(string $modelType): array
    {
        $model = new ($this->resolve($modelType));

        $rows = DB::connection($model->getConnectionName())->select(
            'select column_name as `name`, column_type as `type`, column_comment as `comment`, is_nullable as `nullable`
             from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position',
            [$model->getTable()]
        );

        return collect($rows)
            ->reject(fn ($row) => in_array($row->name, self::HIDDEN, true))
            ->map(fn ($row) => [
                'name' => $row->name,
                'type' => $row->type,
                'comment' => $row->comment !== '' ? $row->comment : null,
                'nullable' => $row->nullable === 'YES',
            ])->values()->all();
    }
}
