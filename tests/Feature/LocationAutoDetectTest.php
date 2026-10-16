<?php

namespace Tests\Feature;

use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\LocationBoundaryService;
use Database\Seeders\ParepareLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — Automatic Kecamatan/Kelurahan detection from a map point.
 *
 * Covers the OPTIONAL suggestion layer on top of the REQUIRED administrative
 * location:
 *   - the bundled boundary GeoJSON exists and is valid (MIT-licensed source)
 *   - every bundled code maps to a real master row (no dangling ids)
 *   - the server payload resolves codes → current database ids + names
 *   - the boundary file / master data degrade gracefully (empty FeatureCollection)
 *   - the create / edit / operator pages embed the payload + listener
 *   - the suggestion is UX-ONLY: the pair stays REQUIRED and server-validated
 *
 * Geometry is never asserted pixel-by-pixel; the tests assert the CONTRACT
 * (shape of the payload, code resolution, graceful degradation) that the
 * frontend relies on.
 */
class LocationAutoDetectTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $operator;

    private DinasUnit $unit;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
        ]);
        $this->unit = DinasUnit::create([
            'name' => 'Dinas Uji Deteksi', 'code' => 'UJI-DETECT',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true,
            'dinas_unit_id' => $this->unit->id,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Deteksi', 'slug' => 'infrastruktur-deteksi',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->category->dinasUnits()->attach($this->unit->id);
    }

    // =========================================================================
    // §1 — Bundled dataset integrity
    // =========================================================================

    public function test_bundled_boundary_file_exists_and_is_valid_geojson(): void
    {
        $file = config('map.boundaries.file');
        $this->assertIsString($file);
        $this->assertFileExists($file);

        $doc = json_decode((string) file_get_contents($file), true);

        $this->assertIsArray($doc);
        $this->assertSame('FeatureCollection', $doc['type']);
        $this->assertCount(22, $doc['features']);
    }

    public function test_every_bundled_feature_carries_kemendagri_code_name_and_geometry(): void
    {
        $doc = json_decode((string) file_get_contents(config('map.boundaries.file')), true);

        foreach ($doc['features'] as $feature) {
            $props = $feature['properties'];
            $this->assertMatchesRegularExpression('/^73\.72\.\d{2}\.\d{4}$/', $props['code']);
            $this->assertNotSame('', $props['name']);
            $this->assertSame(substr($props['code'], 0, 8), $props['kecamatan_code']);
            $this->assertContains($feature['geometry']['type'], ['Polygon', 'MultiPolygon']);
        }
    }

    public function test_bundled_codes_match_the_parepare_seeder_master(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $doc = json_decode((string) file_get_contents(config('map.boundaries.file')), true);
        $codes = array_column(array_column($doc['features'], 'properties'), 'code');

        $this->assertCount(22, $codes);
        $this->assertSame([], array_diff($codes, Kelurahan::pluck('code')->all()));
        $this->assertSame([], array_diff(Kelurahan::pluck('code')->all(), $codes));
    }

    // =========================================================================
    // §2 — Server payload resolution
    // =========================================================================

    public function test_service_resolves_codes_to_current_database_ids(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $payload = app(LocationBoundaryService::class)->payload();

        $this->assertSame('FeatureCollection', $payload['type']);
        $this->assertCount(22, $payload['features']);

        $byCode = [];
        foreach ($payload['features'] as $feature) {
            $byCode[$feature['properties']['code']] = $feature['properties'];
        }

        $kelurahan = Kelurahan::where('code', '73.72.01.1004')->firstOrFail();
        $this->assertSame($kelurahan->id, $byCode['73.72.01.1004']['kelurahan_id']);
        $this->assertSame($kelurahan->kecamatan_id, $byCode['73.72.01.1004']['kecamatan_id']);
        $this->assertSame('Lompoe', $byCode['73.72.01.1004']['name']);
        $this->assertSame('Bacukiki', $byCode['73.72.01.1004']['kecamatan_name']);
    }

    public function test_service_omits_features_without_a_matching_master_row(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        // Remove one kelurahan → its boundary feature must be dropped so the
        // frontend can never reference a non-existent id.
        $removed = Kelurahan::where('code', '73.72.02.1004')->firstOrFail();
        $removedCode = $removed->code;
        $removed->delete();

        $payload = app(LocationBoundaryService::class)->payload();
        $codes = array_column(array_column($payload['features'], 'properties'), 'code');

        $this->assertCount(21, $payload['features']);
        $this->assertNotContains($removedCode, $codes);
    }

    public function test_service_returns_empty_collection_when_master_data_is_empty(): void
    {
        // No seeding: no Kelurahan rows exist.
        $payload = app(LocationBoundaryService::class)->payload();

        $this->assertSame('FeatureCollection', $payload['type']);
        $this->assertSame([], $payload['features']);
    }

    // =========================================================================
    // §3 — Frontend surface
    // =========================================================================

    public function test_create_page_embeds_boundaries_and_listener(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('boundaries:', $html);
        $this->assertStringContainsString('FeatureCollection', $html);
        $this->assertStringContainsString('@location-point.window="location.suggestFromCoordinates', $html);
        $this->assertStringContainsString('suggestFromCoordinates', $html);
    }

    public function test_create_page_omits_detect_hint_when_no_boundaries(): void
    {
        // No master data → empty FeatureCollection → the hint block is not rendered.
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('boundaries:', $html);
        $this->assertStringNotContainsString('terisi otomatis dari titik lokasi', $html);
    }

    public function test_create_page_keeps_location_pair_required(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // Detection never relaxes the REQUIRED administrative pair.
        $this->assertStringContainsString('name="kecamatan_id"', $html);
        $this->assertStringContainsString('name="kelurahan_id"', $html);
        $this->assertMatchesRegularExpression('/name="kecamatan_id"[\s\S]{0,400}?required/', $html);
        $this->assertMatchesRegularExpression('/name="kelurahan_id"[\s\S]{0,400}?required/', $html);
    }

    public function test_create_page_credits_the_mit_boundary_source(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // MIT requires the notice to travel with the data.
        $this->assertStringContainsString('cahyadsn/wilayah_boundaries', $html);
        $this->assertStringContainsString('MIT', $html);
        $this->assertStringContainsString('data batas indikatif', $html);
    }

    public function test_edit_page_embeds_boundaries_for_the_owner(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $kecamatan = Kecamatan::where('code', '73.72.01')->firstOrFail();
        $kelurahan = Kelurahan::where('code', '73.72.01.1004')->firstOrFail();

        $complaint = \App\Models\Complaint::create([
            'reference_code' => 'LPW-EDT-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan edit deteksi lokasi',
            'description'    => 'Deskripsi laporan edit deteksi lokasi otomatis.',
            'status'         => \App\Enums\ComplaintStatus::Submitted,
            'review_state'   => \App\Enums\ComplaintReviewState::ReturnedToCitizen,
            'kecamatan_id'   => $kecamatan->id,
            'kelurahan_id'   => $kelurahan->id,
            'submitted_at'   => now(),
        ]);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.edit', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('boundaries:', $html);
        $this->assertStringContainsString('@location-point.window="location.suggestFromCoordinates', $html);
    }

    public function test_operator_detail_embeds_boundaries_and_listener(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $kecamatan = Kecamatan::where('code', '73.72.01')->firstOrFail();
        $kelurahan = Kelurahan::where('code', '73.72.01.1004')->firstOrFail();

        $complaint = \App\Models\Complaint::create([
            'reference_code' => 'LPW-DET-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan deteksi lokasi',
            'description'    => 'Deskripsi laporan deteksi lokasi otomatis.',
            'status'         => \App\Enums\ComplaintStatus::Submitted,
            'kecamatan_id'   => $kecamatan->id,
            'kelurahan_id'   => $kelurahan->id,
            'submitted_at'   => now(),
        ]);

        $html = $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('boundaries:', $html);
        $this->assertStringContainsString('@location-point.window="location.suggestFromCoordinates', $html);
    }

    /**
     * Regression guard: the Kelurahan is assigned inside `suggestFromCoordinates`
     * on a deferred tick so the dependent `<option>`s (rendered by `x-for` from
     * the freshly selected Kecamatan) already exist when `x-model` applies it.
     *
     * `locationPicker` is a NESTED property of the top-level `x-data`, so Alpine
     * never injects the `$`-magics onto it — `this.$nextTick` is `undefined` there
     * and throws BEFORE the Kelurahan is set (Kecamatan auto-selects, Kelurahan
     * silently does not). The deferred call MUST use the global `Alpine.nextTick`.
     */
    public function test_location_picker_defers_kelurahan_with_global_alpine_next_tick(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('Alpine.nextTick(', $source);

        // The nested-object magic is unavailable — it must never be CALLED here.
        // (A bare mention inside a comment is fine; a `(...)` invocation is not.)
        $this->assertDoesNotMatchRegularExpression('/this\s*\.\s*\$nextTick\s*\(/', $source);

        // The deferred callback still assigns the resolved Kelurahan id.
        $this->assertMatchesRegularExpression(
            '/Alpine\.nextTick\(\s*\(\)\s*=>\s*\{\s*this\.selectedKelurahanId\s*=\s*kelurahanId;/s',
            $source,
        );
    }

    // =========================================================================
    // §4 — The suggestion stays UX-only (server re-validates)
    // =========================================================================

    public function test_suggested_pair_is_still_validated_server_side(): void
    {
        $this->seed(ParepareLocationSeeder::class);

        $kecamatan = Kecamatan::where('code', '73.72.01')->firstOrFail();
        $wrongKelurahan = Kelurahan::where('code', '73.72.02.1001')->firstOrFail();

        // A crafted mismatched pair (as if detection were bypassed) is REJECTED.
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), [
                'category_id'  => $this->category->id,
                'title'        => 'Laporan deteksi manipulasi pasangan',
                'description'  => 'Deskripsi laporan deteksi manipulasi pasangan.',
                'kecamatan_id' => $kecamatan->id,
                'kelurahan_id' => $wrongKelurahan->id,
            ])
            ->assertSessionHasErrors('kelurahan_id');

        $this->assertDatabaseMissing('complaints', [
            'title' => 'Laporan deteksi manipulasi pasangan',
        ]);
    }
}
