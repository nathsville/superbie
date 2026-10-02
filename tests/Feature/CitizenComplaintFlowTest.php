<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CitizenComplaintFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $otherCitizen;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create([
            'role' => 'masyarakat',
            'is_active' => true,
            'password' => Hash::make('password123'),
        ]);

        $this->otherCitizen = User::factory()->create([
            'role' => 'masyarakat',
            'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan',
            'slug' => 'infrastruktur-jalan',
            'description' => 'Kerusakan jalan raya dan jembatan',
            'dinas_name' => 'Dinas PUPR',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_citizen_can_view_dashboard_and_see_isolated_data(): void
    {
        // Create 2 complaints for current citizen
        Complaint::create([
            'reference_code' => 'LPW-20261001-0001',
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'status' => ComplaintStatus::Submitted,
            'title' => 'Jalan Rusak Depan Rumah',
            'description' => 'Deskripsi jalan rusak di depan rumah warga.',
            'submitted_at' => now(),
        ]);
        Complaint::create([
            'reference_code' => 'LPW-20261001-0002',
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'status' => ComplaintStatus::Resolved,
            'title' => 'Lampu Jalan Padam',
            'description' => 'Deskripsi lampu jalan padam di gang sempit.',
            'submitted_at' => now(),
        ]);

        // Create 1 complaint for other citizen (must not be counted or visible)
        Complaint::create([
            'reference_code' => 'LPW-20261001-0003',
            'reporter_id' => $this->otherCitizen->id,
            'category_id' => $this->category->id,
            'status' => ComplaintStatus::Submitted,
            'title' => 'Sampah Menumpuk di Pasar',
            'description' => 'Deskripsi sampah menumpuk di area pasar.',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 2);
        $response->assertViewHas('laporanProses', 1);
        $response->assertViewHas('laporanSelesai', 1);
        $response->assertViewHas('laporanDitolak', 0);
        $response->assertSee('Jalan Rusak Depan Rumah');
        $response->assertSee('Lampu Jalan Padam');
        $response->assertDontSee('Sampah Menumpuk di Pasar');
    }

    public function test_citizen_can_view_history(): void
    {
        Complaint::create([
            'reference_code' => 'LPW-20261001-0004',
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'status' => ComplaintStatus::Submitted,
            'title' => 'Riwayat Laporan Saya 1',
            'description' => 'Deskripsi riwayat laporan saya pertama.',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.history'));

        $response->assertOk();
        $response->assertSee('Riwayat Laporan Saya 1');
    }

    public function test_citizen_can_view_create_page(): void
    {
        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.create'));

        $response->assertOk();
        $response->assertSee('Infrastruktur Jalan');
        $response->assertSee('Dinas PUPR');
    }

    public function test_citizen_can_create_complaint_with_attachment(): void
    {
        Storage::fake('private');

        $file = UploadedFile::fake()->create('bukti_laporan.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title' => 'Jalan Berlubang di Jl. Bau Massepe',
            'description' => 'Lubang jalan sedalam 15 cm membahayakan pengendara motor saat malam hari.',
            'location_text' => 'Jl. Bau Massepe No. 45 Parepare',
            'attachments' => [$file],
        ]);

        $this->assertDatabaseHas('complaints', [
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'title' => 'Jalan Berlubang di Jl. Bau Massepe',
            'location_text' => 'Jl. Bau Massepe No. 45 Parepare',
            'status' => 'submitted',
        ]);

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->first();
        $this->assertNotNull($complaint);
        $this->assertMatchesRegularExpression('/^LPW-\d{8}-[A-Z0-9]{4}$/', $complaint->reference_code);

        // Verify status history
        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'changed_by' => $this->citizen->id,
        ]);

        // Verify attachment
        $this->assertDatabaseHas('complaint_attachments', [
            'complaint_id' => $complaint->id,
            'original_name' => 'bukti_laporan.jpg',
            'disk' => 'private',
        ]);

        $attachment = ComplaintAttachment::where('complaint_id', $complaint->id)->first();
        Storage::disk('private')->assertExists($attachment->path);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->citizen->id,
            'action' => 'complaint_created',
            'subject_type' => 'complaint',
            'subject_id' => $complaint->id,
        ]);

        $response->assertRedirect(route('citizen.complaint.show', $complaint));
        $response->assertSessionHas('success');
    }

    public function test_citizen_cannot_create_complaint_with_inactive_category(): void
    {
        $inactiveCategory = ComplaintCategory::create([
            'name' => 'Kategori Nonaktif',
            'slug' => 'kategori-nonaktif',
            'description' => 'Testing nonaktif',
            'dinas_name' => 'Dinas Uji',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $inactiveCategory->id,
            'title' => 'Laporan Kategori Nonaktif',
            'description' => 'Deskripsi cukup panjang untuk validasi.',
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('complaints', [
            'title' => 'Laporan Kategori Nonaktif',
        ]);
    }

    public function test_citizen_can_view_own_complaint(): void
    {
        $complaint = Complaint::create([
            'reference_code' => 'LPW-20261001-TEST',
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'title' => 'Laporan Milik Saya',
            'description' => 'Deskripsi laporan milik saya.',
            'status' => ComplaintStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('LPW-20261001-TEST');
        $response->assertSee('Laporan Milik Saya');
    }

    public function test_citizen_cannot_view_another_citizens_complaint(): void
    {
        $otherComplaint = Complaint::create([
            'reference_code' => 'LPW-20261001-OTHR',
            'reporter_id' => $this->otherCitizen->id,
            'category_id' => $this->category->id,
            'title' => 'Laporan Rahasia Tetangga',
            'description' => 'Deskripsi rahasia milik orang lain.',
            'status' => ComplaintStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.show', $otherComplaint));

        $response->assertForbidden();
    }

    public function test_citizen_cannot_see_internal_notes_in_complaint_view(): void
    {
        $complaint = Complaint::create([
            'reference_code' => 'LPW-20261001-NOTE',
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'title' => 'Laporan dengan Berbagai Catatan',
            'description' => 'Deskripsi laporan catatan.',
            'status' => ComplaintStatus::Submitted,
            'submitted_at' => now(),
        ]);

        // Internal note (should NOT be visible to citizen)
        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id' => null,
            'visibility' => NoteVisibility::Internal,
            'body' => 'CATATAN RAHASIA INTERNAL PETUGAS - JANGAN BOCOR KE WARGA',
        ]);

        // Public response (MUST be visible to citizen)
        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id' => null,
            'visibility' => NoteVisibility::PublicResponse,
            'body' => 'TANGGAPAN RESMI: Laporan telah diterima dan petugas sedang menuju lokasi.',
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('TANGGAPAN RESMI: Laporan telah diterima dan petugas sedang menuju lokasi.');
        $response->assertDontSee('CATATAN RAHASIA INTERNAL PETUGAS - JANGAN BOCOR KE WARGA');
    }

    public function test_citizen_can_view_and_update_profile(): void
    {
        $response = $this->actingAs($this->citizen)->get(route('citizen.profile.edit'));
        $response->assertOk();
        $response->assertSee($this->citizen->name);

        $updateResponse = $this->actingAs($this->citizen)->patch(route('citizen.profile.update'), [
            'name' => 'Nama Baru Masyarakat',
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $this->citizen->id,
            'name' => 'Nama Baru Masyarakat',
        ]);
    }

    public function test_daily_report_limit_enforced_when_configured(): void
    {
        AppSetting::set('daily_report_limit', 1);

        // 1st report today
        Complaint::create([
            'reference_code' => 'LPW-20261001-DL01',
            'reporter_id' => $this->citizen->id,
            'category_id' => $this->category->id,
            'title' => 'Laporan Pertama Hari Ini',
            'description' => 'Deskripsi laporan pertama.',
            'status' => ComplaintStatus::Submitted,
            'submitted_at' => now(),
        ]);

        // Try 2nd report today
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title' => 'Laporan Melebihi Batas Harian',
            'description' => 'Deskripsi laporan kedua hari ini.',
        ]);

        $response->assertSessionHasErrors('daily_limit');
    }
}
