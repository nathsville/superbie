<?php

namespace Tests\Feature;

use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — "/super-admin/dinas" audit remediation (D1–D9) regression guard.
 *
 * Pins the fixes applied after the audit of the Dinas/Unit master-data surface.
 * The Dinas CRUD *behaviour* (authorization, delete-protection, routing,
 * historical stability) is covered by DinasUnitRoutingTest and is NOT re-tested
 * here — this file guards the correctness, escaping and accessibility fixes.
 *
 *   D1  A crafted ARRAY query param (`?search[]=x`) is ignored → HTTP 200
 *       (never "Array to string conversion" 500). Systemic: also covers the
 *       sibling list pages (kategori/users/kecamatan/kelurahan/audit) + operator.
 *   D2  An INACTIVE unit renders its select with "Nonaktif" selected, so saving
 *       the form as-rendered never silently flips it to Aktif.
 *   D3  The table has column scopes, a caption and section headings.
 *   D4  No `text-[#94A3B8]` slate-400 text remains.
 *   D5  Decorative SVGs are hidden from assistive technology.
 *   D6  The filter `role="search"` form has an accessible name.
 *   D7  No inline `on*` handlers (delete confirmation uses Alpine).
 *   D8  A crafted array field on POST is rejected (422 redirect), never
 *       silently coerced (which would create an inactive unit).
 *   D9  The header reports the GRAND total, independent of active filters.
 */
class SuperAdminDinasAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function makeDinas(array $overrides = []): DinasUnit
    {
        return DinasUnit::create(array_merge([
            'name' => 'Dinas Uji', 'code' => 'UJI-' . uniqid(),
            'is_active' => true, 'sort_order' => 0,
        ], $overrides));
    }

    // ── D1 — crafted array query params are ignored (no 500) ─────────────────

    public function test_array_query_params_do_not_crash_dinas_index(): void
    {
        $this->makeDinas();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.index') . '?search[]=a')
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.index') . '?status[]=active')
            ->assertOk();
    }

    /**
     * The same defect class existed on every sibling list page. Guard them all
     * so a future regression is caught even if it is introduced elsewhere.
     */
    public function test_array_query_params_do_not_crash_sibling_list_pages(): void
    {
        $this->makeDinas();

        foreach ([
            'super-admin.categories.index',
            'super-admin.users.index',
            'super-admin.kecamatan.index',
            'super-admin.kelurahan.index',
            'super-admin.audit.index',
        ] as $route) {
            $this->actingAs($this->superAdmin)
                ->get(route($route) . '?search[]=a')
                ->assertOk();
        }
    }

    // ── D9 — header reports the GRAND total ──────────────────────────────────

    public function test_header_total_is_grand_total_not_filtered_count(): void
    {
        $this->makeDinas(['name' => 'Dinas Alpha', 'code' => 'ALPHA']);
        $this->makeDinas(['name' => 'Dinas Beta', 'code' => 'BETA']);
        $this->makeDinas(['name' => 'Dinas Gamma', 'code' => 'GAMMA']);

        // A filter matching only ONE row must still report the grand total (3).
        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.index') . '?search=Alpha')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Total: <strong>3</strong> Dinas/Unit', $html);
        // …and it must disclose that the list itself is filtered.
        $this->assertStringContainsString('hasil terfilter', $html);
    }

    // ── D3 + D4 + D5 + D6 + D7 — index markup ────────────────────────────────

    public function test_index_has_table_semantics_headings_and_no_inline_handlers(): void
    {
        $this->makeDinas();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.index'))
            ->assertOk()
            ->getContent();

        // D3 — 7 columns scoped + a caption + section headings.
        $this->assertSame(7, substr_count($html, 'scope="col"'));
        $this->assertStringContainsString('<caption class="sr-only">', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, '<h2'));

        // D4 — contrast.
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);

        // D5 — every SVG is decorative-safe.
        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => !str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare));

        // D6 — the search form is labelled.
        $this->assertStringContainsString('role="search" aria-label="Pencarian dan filter Dinas/Unit"', $html);

        // D7 — no inline event handlers.
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/', $html));
    }

    // ── D2 — inactive unit select state ──────────────────────────────────────

    public function test_inactive_unit_edit_renders_nonaktif_selected(): void
    {
        $unit = $this->makeDinas(['is_active' => false]);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.edit', $unit))
            ->assertOk()
            ->getContent();

        preg_match('/<select id="is_active".*?<\/select>/s', $html, $m);
        $this->assertNotEmpty($m, 'is_active select not found');
        $this->assertStringContainsString('value="0" selected', $m[0], 'Inactive unit must render Nonaktif selected.');
        $this->assertStringNotContainsString('value="1" selected', $m[0]);
    }

    public function test_active_unit_edit_renders_aktif_selected(): void
    {
        $unit = $this->makeDinas(['is_active' => true]);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.edit', $unit))
            ->assertOk()
            ->getContent();

        preg_match('/<select id="is_active".*?<\/select>/s', $html, $m);
        $this->assertStringContainsString('value="1" selected', $m[0]);
        $this->assertStringNotContainsString('value="0" selected', $m[0]);
    }

    public function test_saving_inactive_unit_as_rendered_keeps_it_inactive(): void
    {
        $unit = $this->makeDinas(['is_active' => false]);

        // The browser submits the SELECTED option ("0") after the D2 fix.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.dinas.update', $unit), [
                'name' => $unit->name, 'code' => $unit->code, 'is_active' => '0', 'sort_order' => 0,
            ])->assertRedirect();

        $this->assertFalse((bool) $unit->fresh()->is_active);
    }

    // ── D7 + D4 + D5 — edit page ─────────────────────────────────────────────

    public function test_edit_page_has_no_inline_handlers_and_hidden_svgs(): void
    {
        $unit = $this->makeDinas();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dinas.edit', $unit))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, preg_match_all('/\son[a-z]+="/', $html));
        $this->assertStringContainsString('@submit="if (! confirm(', $html);

        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => !str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare));

        $this->assertStringNotContainsString('text-[#94A3B8]', $html);
    }

    // ── D8 — array POST fields are rejected, not coerced ─────────────────────

    public function test_array_post_fields_are_rejected_and_create_nothing(): void
    {
        // A crafted `is_active[]=1` must NOT be silently coerced to `false`
        // (which would create an INACTIVE unit); it must fail validation.
        foreach ([
            ['name' => ['x'], 'is_active' => '1'],
            ['name' => 'Valid', 'code' => ['y'], 'is_active' => '1'],
            ['name' => 'Valid', 'is_active' => ['1']],
            ['name' => 'Valid', 'sort_order' => ['3']],
        ] as $payload) {
            $this->actingAs($this->superAdmin)
                ->post(route('super-admin.dinas.store'), $payload)
                ->assertRedirect();
        }

        $this->assertSame(0, DinasUnit::count());
    }

    public function test_valid_create_and_deactivate_still_work(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.dinas.store'), [
            'name' => 'Dinas Baru', 'code' => 'NEW01', 'is_active' => '1', 'sort_order' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('dinas_units', ['name' => 'Dinas Baru', 'code' => 'NEW01', 'is_active' => 1]);

        $unit = $this->makeDinas(['is_active' => true]);
        $this->actingAs($this->superAdmin)->put(route('super-admin.dinas.update', $unit), [
            'name' => $unit->name, 'code' => $unit->code, 'is_active' => '0', 'sort_order' => 0,
        ])->assertRedirect();
        $this->assertFalse((bool) $unit->fresh()->is_active);
    }
}
