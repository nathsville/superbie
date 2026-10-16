<?php

namespace App\Services\Weather;

/**
 * WeatherLocationRegistry — reads the monitored locations from configuration.
 *
 * Multi-location support: `config('weather.locations')` is an ORDERED list of
 * location definitions, each carrying its own BMKG `adm4` code AND its own
 * maritime `marine_area_code`. This registry resolves them by key and exposes
 * the default one.
 *
 * IMPORTANT: this class only reads CONFIGURATION. It never invents a location
 * and never fabricates a code. An entry with a missing `key` or `adm4` is
 * dropped so a malformed config cannot silently produce a bad sync.
 *
 * OWNER DECISION: only Kota Parepare is configured (config/weather.php). The
 * capability exists so a further location is a CONFIG change, not a code change.
 */
class WeatherLocationRegistry
{
    /**
     * All VALID configured locations, in order.
     *
     * @return list<array{key:string, name:string, adm4:string, latitude:?string, longitude:?string, marine_area_code:?string, is_default:bool}>
     */
    public function all(): array
    {
        $raw = config('weather.locations');

        if (! is_array($raw)) {
            return [];
        }

        $locations = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;
            $adm4 = $entry['adm4'] ?? null;

            // A location without a stable key or a BMKG code is unusable; drop
            // it rather than guessing.
            if (! is_string($key) || trim($key) === '' || ! is_string($adm4) || trim($adm4) === '') {
                continue;
            }

            $locations[] = [
                'key' => trim($key),
                'name' => is_string($entry['name'] ?? null) && trim($entry['name']) !== ''
                    ? trim($entry['name'])
                    : trim($key),
                'adm4' => trim($adm4),
                'latitude' => isset($entry['latitude']) ? (string) $entry['latitude'] : null,
                'longitude' => isset($entry['longitude']) ? (string) $entry['longitude'] : null,
                'marine_area_code' => is_string($entry['marine_area_code'] ?? null) && trim($entry['marine_area_code']) !== ''
                    ? trim($entry['marine_area_code'])
                    : null,
                'is_default' => (bool) ($entry['is_default'] ?? false),
            ];
        }

        return $locations;
    }

    /**
     * The key of the default location (the entry flagged `is_default`, else the
     * first valid entry). NULL when nothing is configured.
     */
    public function defaultKey(): ?string
    {
        $locations = $this->all();

        if ($locations === []) {
            return null;
        }

        foreach ($locations as $location) {
            if ($location['is_default']) {
                return $location['key'];
            }
        }

        return $locations[0]['key'];
    }

    /**
     * Find one location by its key.
     *
     * @return array{key:string, name:string, adm4:string, latitude:?string, longitude:?string, marine_area_code:?string, is_default:bool}|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->all() as $location) {
            if ($location['key'] === $key) {
                return $location;
            }
        }

        return null;
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * Cache key for a single location's public snapshot. Centralised so the
     * read service and the sync service can never disagree on the key shape.
     */
    public function cacheKey(string $key): string
    {
        return 'weather:snapshot:'.$key;
    }

    /**
     * Cache key for the public location index (the list shown in the picker).
     */
    public function indexCacheKey(): string
    {
        return 'weather:snapshot:index';
    }
}
