<?php

namespace App\Services;

use App\Models\Kecamatan;
use App\Models\Kelurahan;

/**
 * Wilayah-boundary resolver for the OPTIONAL automatic Kecamatan/Kelurahan
 * suggestion on the complaint form.
 *
 * Reads the bundled Parepare kelurahan boundary GeoJSON (MIT-licensed source,
 * see `resources/data/README.md`) and resolves every feature to the CURRENT
 * master rows in the database by Kemendagri `code`. Features whose code has no
 * matching Kelurahan row are DROPPED, so the frontend can never select a
 * non-existent id.
 *
 * This is a UX SUGGESTION layer only. The chosen Kecamatan/Kelurahan stay
 * REQUIRED and are always re-validated server-side (StoreComplaintRequest /
 * UpdateComplaintRequest); the browser result is never trusted.
 */
class LocationBoundaryService
{
    /**
     * Parsed raw FeatureCollection (cached per process). `null` until first read.
     *
     * @var array{type: string, features: list<array<string, mixed>>}|null
     */
    private ?array $raw = null;

    /**
     * Whether the bundled boundary file exists and could be read.
     */
    public function isAvailable(): bool
    {
        $file = config('map.boundaries.file');

        return is_string($file) && is_file($file);
    }

    /**
     * GeoJSON FeatureCollection whose feature `properties` carry the resolved
     * database ids (`kecamatan_id`, `kelurahan_id`) plus display names.
     *
     * Features with an unmatched `code` are omitted. When the master data is
     * empty (or the file is missing) an empty FeatureCollection is returned and
     * the frontend simply disables detection.
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function payload(): array
    {
        $empty = ['type' => 'FeatureCollection', 'features' => []];

        if (! $this->isAvailable()) {
            return $empty;
        }

        $kelurahan = Kelurahan::query()
            ->whereNotNull('code')
            ->get(['id', 'code', 'kecamatan_id'])
            ->keyBy('code');

        if ($kelurahan->isEmpty()) {
            return $empty;
        }

        $kecamatanNames = Kecamatan::query()->pluck('name', 'id');

        $features = [];
        foreach ($this->raw()['features'] as $feature) {
            $code = $feature['properties']['code'] ?? null;
            $match = $code !== null ? $kelurahan->get($code) : null;

            if ($match === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'code'           => $code,
                    'name'           => $feature['properties']['name'] ?? '',
                    'kecamatan_id'   => $match->kecamatan_id,
                    'kecamatan_name' => $kecamatanNames[$match->kecamatan_id] ?? '',
                    'kelurahan_id'   => $match->id,
                ],
                'geometry' => $feature['geometry'] ?? null,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * Lazily read + decode the bundled GeoJSON file.
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private function raw(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }

        $file = config('map.boundaries.file');
        $decoded = is_string($file) && is_file($file)
            ? json_decode((string) file_get_contents($file), true)
            : null;

        $this->raw = is_array($decoded) && is_array($decoded['features'] ?? null)
            ? $decoded
            : ['type' => 'FeatureCollection', 'features' => []];

        return $this->raw;
    }
}
