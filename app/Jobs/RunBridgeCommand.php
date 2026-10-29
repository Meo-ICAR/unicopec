<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Esegue un comando in whitelist per conto di UnicoBPM e registra l'esito, leggibile con GET /api/commands/runs/{id}. */
class RunBridgeCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param array<string, mixed> $options */
    public function __construct(public string $runId, public string $command, public array $options) {}

    public static function key(string $runId): string
    {
        return "bridge:run:{$runId}";
    }

    public function handle(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()->toIso8601String()]);

        try {
            $exit = Artisan::call($this->command, collect($this->options)->mapWithKeys(fn ($v, $k) => ["--{$k}" => $v])->all());

            $this->update(['status' => $exit === 0 ? 'succeeded' : 'failed', 'exit_code' => $exit, 'output' => mb_substr(Artisan::output(), -4000), 'finished_at' => now()->toIso8601String()]);
        } catch (Throwable $e) {
            $this->update(['status' => 'failed', 'output' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()->toIso8601String()]);
        }
    }

    /** @param array<string, mixed> $changes */
    private function update(array $changes): void
    {
        Cache::put(self::key($this->runId), array_merge(Cache::get(self::key($this->runId), []), $changes), now()->addDay());
    }
}
