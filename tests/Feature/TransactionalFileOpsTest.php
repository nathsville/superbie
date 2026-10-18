<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\Market;
use App\Models\MarketStore;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * Audit PROMPT_28 — package P1 (S-02/S-04/S-05).
 *
 * File operations must never depend on the database transaction: a rollback
 * after a file delete leaves a row pointing at a missing file (unrecoverable),
 * and a rollback after a file write leaves an orphan. These tests force the
 * failure paths (model events / notification emit) and observe the disk:
 *
 *   · market store create/update/delete — public photo disk
 *   · complaint create/resubmission — private attachment disk
 *
 * Plus the two index-level findings:
 *   · S-04 — the price entry screen must not wrap `effective_date` in DATE()
 *   · S-05 — audit_logs carries the (subject_type, subject_id) purge index
 */
class TransactionalFileOpsTest extends TestCase
{
    use CreatesLocationFixtures;
    use RefreshDatabase;

    private User $superAdmin;

    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        // Failure injection is registered per test through model events.
        MarketStore::flushEventListeners();

        parent::tearDown();
    }

    private function makeMarket(): Market
    {
        static $seq = 0;
        $n = ++$seq;

        return Market::create([
            'name'      => 'Pasar Uji P1 ' . $n,
            'slug'      => 'pasar-uji-p1-' . $n,
            'is_active' => true,
        ]);
    }

    private function makeStore(string $photoPath): MarketStore
    {
        return MarketStore::create([
            'market_id'  => $this->makeMarket()->id,
            'name'       => 'Toko Uji P1',
            'photo_path' => $photoPath,
            'is_active'  => true,
        ]);
    }

    private function makeCategory(): ComplaintCategory
    {
        static $seq = 0;
        $n = ++$seq;

        return ComplaintCategory::create([
            'name'       => 'Kategori Uji P1 ' . $n,
            'slug'       => 'kategori-uji-p1-' . $n,
            'is_active'  => true,
            'sort_order' => $n,
        ]);
    }

    private function makeReturnedComplaint(ComplaintCategory $category): Complaint
    {
        static $seq = 0;
        $n = ++$seq;

        return Complaint::create([
            'reference_code' => 'LPW-P1-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $category->id,
            'title'          => 'Laporan uji P1 ' . $n,
            'description'    => 'Deskripsi laporan uji paket P1 yang cukup panjang.',
            'status'         => ComplaintStatus::Submitted,
            'review_state'   => ComplaintReviewState::ReturnedToCitizen,
            'submitted_at'   => now(),
        ]);
    }

    // =========================================================================
    // S-02 — MARKET STORE PHOTOS
    // =========================================================================

    public function test_market_store_create_removes_the_new_photo_when_the_transaction_fails(): void
    {
        Storage::fake('public');

        MarketStore::creating(function () {
            throw new RuntimeException('forced rollback (test)');
        });

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-stores.store'), [
                'market_id' => $this->makeMarket()->id,
                'name'      => 'Toko Gagal Simpan',
                'photo'     => UploadedFile::fake()->image('gagal.jpg', 320, 240),
            ])
            ->assertStatus(500);

        $this->assertDatabaseMissing('market_stores', ['name' => 'Toko Gagal Simpan']);
        $this->assertSame(
            [],
            Storage::disk('public')->allFiles(),
            'S-02: a rolled-back create must not leave an orphaned photo'
        );
    }

    public function test_market_store_update_keeps_the_old_photo_when_the_transaction_fails(): void
    {
        Storage::fake('public');

        $store = $this->makeStore('siphp/stores/lama.jpg');
        Storage::disk('public')->put('siphp/stores/lama.jpg', 'old-photo');

        MarketStore::updating(function () {
            throw new RuntimeException('forced rollback (test)');
        });

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.market-stores.update', $store), [
                'name'  => $store->name,
                'photo' => UploadedFile::fake()->image('baru.jpg', 320, 240),
            ])
            ->assertStatus(500);

        $store->refresh();
        $this->assertSame('siphp/stores/lama.jpg', $store->photo_path);
        Storage::disk('public')->assertExists('siphp/stores/lama.jpg');
        $this->assertSame(
            ['siphp/stores/lama.jpg'],
            Storage::disk('public')->allFiles(),
            'S-02: the fresh upload is compensated; the replaced file is kept until the commit'
        );
    }

    public function test_market_store_destroy_keeps_the_photo_when_the_transaction_fails(): void
    {
        Storage::fake('public');

        $store = $this->makeStore('siphp/stores/kukuh.jpg');
        Storage::disk('public')->put('siphp/stores/kukuh.jpg', 'photo');

        MarketStore::deleting(function () {
            throw new RuntimeException('forced rollback (test)');
        });

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-stores.destroy', $store))
            ->assertStatus(500);

        $this->assertDatabaseHas('market_stores', ['id' => $store->id]);
        Storage::disk('public')->assertExists('siphp/stores/kukuh.jpg');
        $this->assertSame(
            ['siphp/stores/kukuh.jpg'],
            Storage::disk('public')->allFiles(),
            'S-02: the file is deleted only after the commit'
        );
    }

    // =========================================================================
    // S-02 — COMPLAINT ATTACHMENTS
    // =========================================================================

    public function test_complaint_create_compensates_attachment_files_when_the_transaction_fails(): void
    {
        Storage::fake('private');

        // M1 emit runs LAST inside the create transaction: making it throw
        // proves the rollback happens after the attachments were written.
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('emit')->andThrow(new RuntimeException('forced rollback (test)'));
        });

        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), [
                'category_id' => $this->makeCategory()->id,
                'title'       => 'Laporan uji kompensasi lampiran',
                'description' => 'Deskripsi laporan untuk menguji kompensasi lampiran gagal.',
                'attachments' => [UploadedFile::fake()->image('bukti.jpg', 120, 120)],
                ...$this->locationPayload(),
            ])
            ->assertStatus(500);

        $this->assertDatabaseCount('complaints', 0);
        $this->assertSame(
            [],
            Storage::disk('private')->allFiles(),
            'S-02: a rolled-back create must not leave orphaned attachment files'
        );
    }

    public function test_complaint_resubmission_restores_files_and_rows_when_the_transaction_fails(): void
    {
        Storage::fake('private');

        $category  = $this->makeCategory();
        $complaint = $this->makeReturnedComplaint($category);

        $remove = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/uji/remove.jpg',
            'original_name' => 'remove.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 1000,
        ]);
        Storage::disk('private')->put('complaints/uji/remove.jpg', 'old-attachment');

        // M4 emit runs LAST inside the resubmission transaction.
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('emit')->andThrow(new RuntimeException('forced rollback (test)'));
        });

        $this->actingAs($this->citizen)
            ->put(route('citizen.complaint.update', $complaint), [
                'category_id'        => $category->id,
                'title'              => 'Judul laporan diperbaiki',
                'description'        => 'Deskripsi laporan yang sudah diperbaiki oleh pelapor.',
                'remove_attachments' => [$remove->id],
                'attachments'        => [UploadedFile::fake()->image('baru.jpg', 100, 100)],
                ...$this->locationPayload(),
            ])
            ->assertStatus(500);

        $this->assertDatabaseHas('complaint_attachments', ['id' => $remove->id]);
        Storage::disk('private')->assertExists('complaints/uji/remove.jpg');
        $this->assertSame(
            ['complaints/uji/remove.jpg'],
            Storage::disk('private')->allFiles(),
            'S-02: removed files are kept until the commit; the fresh upload is compensated'
        );
    }

    // =========================================================================
    // S-04 / S-05 — INDEX-LEVEL FINDINGS
    // =========================================================================

    public function test_price_entry_screen_reads_effective_date_through_the_index(): void
    {
        $market = $this->makeMarket();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.market-prices.catat', ['market' => $market->id, 'date' => '2026-10-15']))
            ->assertOk();

        $priceQueries = array_values(array_filter(
            $queries,
            fn (string $sql) => str_contains($sql, 'effective_date')
        ));

        $this->assertNotEmpty($priceQueries, 'the entry screen must read the stored rows for the day');

        foreach ($priceQueries as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/date\(\s*[`"\']?effective_date/i',
                $sql,
                'S-04: DATE(effective_date) disables the (market_id, effective_date) index'
            );
        }
    }

    public function test_audit_logs_carries_the_subject_purge_index(): void
    {
        $this->assertTrue(
            Schema::hasIndex('audit_logs', 'audit_logs_subject_index'),
            'S-05: the retention purge deletes per (subject_type, subject_id) and needs this index'
        );

        $this->assertTrue(
            Schema::hasIndex('audit_logs', ['subject_type', 'subject_id']),
            'S-05: the index must cover (subject_type, subject_id)'
        );
    }
}
