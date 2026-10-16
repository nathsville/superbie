<?php

namespace Tests\Concerns;

use App\Models\Kecamatan;
use App\Models\Kelurahan;

/**
 * Shared test fixture helper for the FINAL location rule.
 *
 * Kecamatan/Kelurahan are REQUIRED for every new complaint, so existing tests
 * that POST to `citizen.complaint.store` must supply a valid pair. These are
 * test-only fixtures (arbitrary labels), NOT official BPS data and NOT seeded
 * into the application.
 */
trait CreatesLocationFixtures
{
    /**
     * Create a Kecamatan + Kelurahan pair and return [kecamatanId, kelurahanId].
     *
     * @return array{0:int,1:int}
     */
    protected function makeLocationPair(string $kecamatanName = 'Kecamatan Uji', string $kelurahanName = 'Kelurahan Uji'): array
    {
        $kecamatan = Kecamatan::create(['name' => $kecamatanName]);
        $kelurahan = Kelurahan::create([
            'kecamatan_id' => $kecamatan->id,
            'name'         => $kelurahanName,
        ]);

        return [$kecamatan->id, $kelurahan->id];
    }

    /**
     * The two location fields to merge into a complaint POST payload.
     *
     * @return array{kecamatan_id:int,kelurahan_id:int}
     */
    protected function locationPayload(string $kecamatanName = 'Kecamatan Uji', string $kelurahanName = 'Kelurahan Uji'): array
    {
        [$kecamatanId, $kelurahanId] = $this->makeLocationPair($kecamatanName, $kelurahanName);

        return [
            'kecamatan_id' => $kecamatanId,
            'kelurahan_id' => $kelurahanId,
        ];
    }
}
