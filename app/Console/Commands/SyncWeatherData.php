<?php

namespace App\Console\Commands;

use App\Services\Weather\WeatherSyncService;
use Illuminate\Console\Command;

/**
 * Sync BMKG weather data into the database.
 *
 * Owner decision D-16: synchronisation is available BOTH on a schedule and
 * manually (the Super Admin surface triggers the same WeatherSyncService).
 *
 * This command performs no work of its own beyond delegating to the service,
 * which:
 *   - guards against concurrent runs with `Cache::lock('weather:sync')`;
 *   - writes atomically inside a DB transaction;
 *   - invalidates the public read cache.
 *
 * Safety: `--force` is required in production (mirrors the retention purge
 * convention) so an accidental interactive run cannot hit the external API in
 * a production environment.
 */
class SyncWeatherData extends Command
{
    protected $signature = 'weather:sync
                            {--force : Wajib untuk menjalankan di environment production}';

    protected $description = 'Sinkronkan data prakiraan cuaca BMKG ke database.';

    public function handle(WeatherSyncService $service): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Menjalankan sinkronisasi cuaca di production memerlukan flag --force.');
            return self::FAILURE;
        }

        $result = $service->sync();

        if (! $result['ok']) {
            $this->error($result['message']);
            return self::FAILURE;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
