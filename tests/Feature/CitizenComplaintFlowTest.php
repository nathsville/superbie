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
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

class CitizenComplaintFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

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
            ...$this->locationPayload(),
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

    public function test_citizen_can_upload_multiple_attachments_at_once(): void
    {
        Storage::fake('private');

        $files = [
            UploadedFile::fake()->create('bukti-1.jpg', 300, 'image/jpeg'),
            UploadedFile::fake()->create('bukti-2.png', 200, 'image/png'),
            UploadedFile::fake()->create('bukti-3.pdf', 100, 'application/pdf'),
        ];

        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id'   => $this->category->id,
            'title'         => 'Laporan dengan Tiga Bukti',
            'description'   => 'Deskripsi laporan dengan tiga berkas lampiran sekaligus.',
            'attachments'   => $files,
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->first();
        $this->assertNotNull($complaint);

        // ALL selected files must persist — not just the last one.
        $this->assertSame(3, $complaint->attachments()->count());
        foreach (['bukti-1.jpg', 'bukti-2.png', 'bukti-3.pdf'] as $name) {
            $this->assertDatabaseHas('complaint_attachments', [
                'complaint_id'  => $complaint->id,
                'original_name' => $name,
            ]);
        }
    }

    public function test_create_page_renders_a_multi_file_attachment_picker(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // Exactly one real multi-file input, correctly named for Laravel arrays.
        $this->assertSame(1, substr_count($html, 'name="attachments[]"'));
        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('type="file"', $html);

        // The accumulating picker is wired up (progressive enhancement).
        $this->assertStringContainsString("x-data=\"attachmentPicker(", $html);
        $this->assertStringContainsString('@change="onChange($event)"', $html);

        // The advertised limit matches the FINAL business rule (10), not a stale "3".
        $maxFiles = (int) config('business_rules.attachment.max_files');
        $this->assertStringContainsString('maks. '.$maxFiles.' berkas', $html);
        $this->assertStringNotContainsString('Maks. 3 berkas', $html);
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
            ...$this->locationPayload(),
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
            'phone_number' => $this->citizen->phone_number,
            'address' => $this->citizen->address,
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $this->citizen->id,
            'name' => 'Nama Baru Masyarakat',
        ]);
    }

    public function test_citizen_can_upload_a_profile_photo(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $this->actingAs($this->citizen)
            ->patch(route('citizen.profile.update'), [
                'name'         => $this->citizen->name,
                'phone_number' => $this->citizen->phone_number,
                'address'      => $this->citizen->address,
                'avatar'       => UploadedFile::fake()->image('selfie.png', 240, 240),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $path = $this->citizen->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_a_profile_photo_removes_the_previous_file(): void
    {        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $this->actingAs($this->citizen)
            ->patch(route('citizen.profile.update'), [
                'name'         => $this->citizen->name,
                'phone_number' => $this->citizen->phone_number,
                'address'      => $this->citizen->address,
                'avatar'       => UploadedFile::fake()->image('first.jpg', 200, 200),
            ]);

        $first = $this->citizen->fresh()->avatar_path;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->citizen)
            ->patch(route('citizen.profile.update'), [
                'name'         => $this->citizen->name,
                'phone_number' => $this->citizen->phone_number,
                'address'      => $this->citizen->address,
                'avatar'       => UploadedFile::fake()->image('second.jpg', 200, 200),
            ]);

        $second = $this->citizen->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_citizen_avatar_validation_error_is_surfaced(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        // A non-image file is rejected, and the redirect back to the profile page
        // renders the error text (previously the failure was silent).
        $this->from(route('citizen.profile.edit'))
            ->actingAs($this->citizen)
            ->followingRedirects()
            ->patch(route('citizen.profile.update'), [
                'name'         => $this->citizen->name,
                'phone_number' => $this->citizen->phone_number,
                'address'      => $this->citizen->address,
                'avatar'       => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertOk()
            ->assertSee('Berkas foto harus berupa gambar.');
    }

    public function test_citizen_profile_auto_opens_edit_mode_on_validation_error(): void
    {
        // A failed submit must re-open edit mode so the errors (rendered inside
        // the edit-only blocks) are actually visible to the user.
        $this->from(route('citizen.profile.edit'))
            ->actingAs($this->citizen)
            ->followingRedirects()
            ->patch(route('citizen.profile.update'), [
                'name'         => '',
                'phone_number' => $this->citizen->phone_number,
                'address'      => $this->citizen->address,
            ])
            ->assertSee('x-data="{ editing: true,', false);
    }

    public function test_legacy_citizen_without_phone_and_address_gets_a_visible_error_on_photo_only_save(): void
    {
        // Regression for the reported "uploaded a photo but nothing changed":
        // a legacy account with NULL phone_number/address cannot save ANY change
        // (phone + address are required for citizens — FINAL rule, Prompt 5B).
        // The failure must be VISIBLE, not silent.
        $legacy = User::factory()->create([
            'role'         => 'masyarakat',
            'is_active'    => true,
            'phone_number' => null,
            'address'      => null,
        ]);

        $this->from(route('citizen.profile.edit'))
            ->actingAs($legacy)
            ->followingRedirects()
            ->patch(route('citizen.profile.update'), [
                'name'   => $legacy->name,
                'avatar' => UploadedFile::fake()->image('selfie.jpg', 200, 200),
            ])
            ->assertOk()
            // Edit mode re-opens and the two blocking requirements are shown.
            ->assertSee('x-data="{ editing: true,', false)
            ->assertSee('Nomor HP wajib diisi.')
            ->assertSee('Alamat wajib diisi.');

        // The photo is NOT silently half-saved: the whole update is atomic.
        $this->assertNull($legacy->fresh()->avatar_path);
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
            ...$this->locationPayload(),
        ]);

        $response->assertSessionHasErrors('daily_limit');
    }
}
