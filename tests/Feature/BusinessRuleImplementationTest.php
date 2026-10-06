<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 5B — Final Business Rule Implementation
 *
 * Covers the finalized business rules:
 *   - Daily report limit (5 / calendar day)
 *   - Identity (NIK 16 digits unique immutable, phone local+international
 *     unique editable, address free text)
 *   - Attachment (optional, max 10, max 20 MB, jpg/jpeg/png/pdf/mp4)
 *   - Category ↔ Dinas/Unit many-to-many
 *   - Privacy / IDOR
 *
 * Retention is BLOCKED (reference timestamp undefined) — see report; no test
 * performs destructive deletion.
 */
class BusinessRuleImplementationTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $otherCitizen;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make('password123'),
            'nik'       => '7373000000001111',
            'phone_number' => '081100000011',
        ]);

        $this->otherCitizen = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur Jalan',
            'slug'       => 'infrastruktur-jalan',
            'description' => 'Kerusakan jalan raya dan jembatan',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function makeComplaint(User $reporter, array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-TEST-' . strtoupper(uniqid()),
            'reporter_id'    => $reporter->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji business rule',
            'description'    => 'Deskripsi laporan pengujian business rule 5B.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function complaintPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title'       => 'Jalan berlubang di Jl. Uji 5B',
            'description' => 'Lubang cukup dalam membahayakan pengendara sepeda motor.',
        ], $overrides);
    }

    // =========================================================================
    // DAILY LIMIT — 5 / calendar day
    // =========================================================================

    public function test_first_report_allowed(): void
    {
        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->complaintPayload());

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    public function test_fifth_report_allowed(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->makeComplaint($this->citizen);
        }

        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->complaintPayload());

        $response->assertSessionHasNoErrors();
        $this->assertSame(5, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    public function test_sixth_report_rejected(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeComplaint($this->citizen);
        }

        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->complaintPayload());

        $response->assertSessionHasErrors('daily_limit');
        $this->assertSame(5, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    public function test_daily_limit_is_per_user_and_independent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeComplaint($this->citizen);
        }

        // Another user still has full quota.
        $response = $this->actingAs($this->otherCitizen)
            ->post(route('citizen.complaint.store'), $this->complaintPayload(['title' => 'Laporan warga lain hari ini']));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('complaints', [
            'reporter_id' => $this->otherCitizen->id,
            'title'       => 'Laporan warga lain hari ini',
        ]);
    }

    public function test_daily_limit_resets_next_calendar_day(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeComplaint($this->citizen, ['submitted_at' => now()->subDay()]);
        }

        // Previous calendar day reports do not count toward today.
        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->complaintPayload());

        $response->assertSessionHasNoErrors();
    }

    // =========================================================================
    // IDENTITY — NIK
    // =========================================================================

    public function test_registration_requires_identity_fields(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Warga Baru',
            'email' => 'wargabaru@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['nik', 'phone_number', 'address']);
    }

    public function test_registration_accepts_valid_identity(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Warga Baru',
            'email' => 'wargabaru2@example.test',
            'nik' => '7373000000009999',
            'phone_number' => '081200000099',
            'address' => 'Jl. Uji 5B No. 1, Parepare',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('citizen.dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'wargabaru2@example.test',
            'nik' => '7373000000009999',
        ]);
    }

    public function test_nik_must_be_exactly_16_digits_and_numeric(): void
    {
        foreach (['123', '12345678901234567', 'abcdefghijklmnop', '73730000000000a1'] as $badNik) {
            $response = $this->post(route('register.store'), [
                'name' => 'Warga',
                'email' => 'bad' . uniqid() . '@example.test',
                'nik' => $badNik,
                'phone_number' => '081300000000',
                'address' => 'Alamat',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrors('nik');
        }
    }

    public function test_nik_must_be_unique(): void
    {
        User::factory()->create(['nik' => '7373000000007777']);

        $response = $this->post(route('register.store'), [
            'name' => 'Warga',
            'email' => 'dupnik@example.test',
            'nik' => '7373000000007777',
            'phone_number' => '081300000077',
            'address' => 'Alamat',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_profile_displays_full_nik(): void
    {
        $response = $this->actingAs($this->citizen)->get(route('citizen.profile.edit'));

        $response->assertOk();
        $response->assertSee('7373000000001111');
    }

    public function test_profile_cannot_modify_nik_even_via_direct_request(): void
    {
        $original = $this->citizen->nik;

        $this->actingAs($this->citizen)->patch(route('citizen.profile.update'), [
            'name' => $this->citizen->name,
            'phone_number' => $this->citizen->phone_number,
            'address' => $this->citizen->address,
            'nik' => '0000000000000000', // malicious attempt
        ]);

        $this->assertSame($original, $this->citizen->fresh()->nik);
    }

    // =========================================================================
    // IDENTITY — PHONE
    // =========================================================================

    public function test_phone_local_format_accepted_and_normalized(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Warga Lokal',
            'email' => 'lokal@example.test',
            'nik' => '7373000000002222',
            'phone_number' => '081200000022',
            'address' => 'Alamat lokal',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'lokal@example.test',
            'phone_number' => '081200000022',
        ]);
    }

    public function test_phone_international_format_normalized_to_local(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Warga Internasional',
            'email' => 'intl@example.test',
            'nik' => '7373000000003333',
            'phone_number' => '+6281200000033',
            'address' => 'Alamat internasional',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        // +6281200000033 and 081200000033 represent the same line → canonical local form.
        $this->assertDatabaseHas('users', [
            'email' => 'intl@example.test',
            'phone_number' => '081200000033',
        ]);
    }

    public function test_phone_must_be_unique(): void
    {
        User::factory()->create(['phone_number' => '081200000044']);

        $response = $this->post(route('register.store'), [
            'name' => 'Warga Duplikat',
            'email' => 'dupphone@example.test',
            'nik' => '7373000000004444',
            'phone_number' => '081200000044',
            'address' => 'Alamat',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('phone_number');
    }

    public function test_profile_can_update_phone(): void
    {
        $this->actingAs($this->citizen)->patch(route('citizen.profile.update'), [
            'name' => $this->citizen->name,
            'phone_number' => '081299999999',
            'address' => $this->citizen->address,
        ])->assertSessionHas('success');

        $this->assertSame('081299999999', $this->citizen->fresh()->phone_number);
    }

    // =========================================================================
    // IDENTITY — ADDRESS
    // =========================================================================

    public function test_address_is_free_text_without_business_max_length(): void
    {
        $longAddress = rtrim(str_repeat('Alamat panjang tanpa batas bisnis. ', 60)); // > 255 chars

        $this->post(route('register.store'), [
            'name' => 'Warga Alamat Panjang',
            'email' => 'panjang@example.test',
            'nik' => '7373000000005555',
            'phone_number' => '081200000055',
            'address' => $longAddress,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        $this->assertSame($longAddress, User::where('email', 'panjang@example.test')->first()->address);
    }

    // =========================================================================
    // ATTACHMENT
    // =========================================================================

    /** @return array<int, UploadedFile> */
    private function files(int $count, string $extension = 'jpg'): array
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $files[] = UploadedFile::fake()->create("lampiran{$i}.{$extension}", 100);
        }
        return $files;
    }

    public function test_assets_for_random_fake_factory_path_is_safe(): void
    {
        // Sanity: attachment optional → complaint without files succeeds.
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->complaintPayload())
            ->assertSessionHasNoErrors();
    }

    public function test_up_to_ten_files_accepted(): void
    {
        Storage::fake('private');

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->complaintPayload([
            'attachments' => $this->files(10),
        ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_eleven_files_rejected(): void
    {
        Storage::fake('private');

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->complaintPayload([
            'attachments' => $this->files(11),
        ]));

        $response->assertSessionHasErrors('attachments');
    }

    public function test_supported_formats_accepted(): void
    {
        Storage::fake('private');

        foreach (['jpg', 'jpeg', 'png', 'pdf', 'mp4'] as $ext) {
            $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->complaintPayload([
                'title' => 'Laporan format ' . $ext,
                'attachments' => [UploadedFile::fake()->create("berkas.{$ext}", 100)],
            ]));

            $response->assertSessionHasNoErrors();
        }
    }

    public function test_unsupported_format_rejected(): void
    {
        Storage::fake('private');

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->complaintPayload([
            'attachments' => [UploadedFile::fake()->create('berkas.txt', 100)],
        ]));

        $response->assertSessionHasErrors('attachments.0');
    }

    public function test_file_under_20mb_accepted_and_over_20mb_rejected(): void
    {
        Storage::fake('private');

        // 20 MB exactly (20480 KB) → accepted.
        $ok = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->complaintPayload([
            'title' => 'Laporan 20MB',
            'attachments' => [UploadedFile::fake()->create('besar.jpg', 20480)],
        ]));
        $ok->assertSessionHasNoErrors();

        // Over 20 MB → rejected.
        $over = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->complaintPayload([
            'title' => 'Laporan 25MB',
            'attachments' => [UploadedFile::fake()->create('kebesaran.jpg', 25600)],
        ]));
        $over->assertSessionHasErrors('attachments.0');
    }

    // =========================================================================
    // CATEGORY ↔ DINAS/UNIT — MANY-TO-MANY
    // =========================================================================

    public function test_category_maps_to_many_dinas_units(): void
    {
        $pupr = DinasUnit::create(['name' => 'Dinas A', 'is_active' => true]);
        $dishub = DinasUnit::create(['name' => 'Dinas B', 'is_active' => true]);

        $this->category->dinasUnits()->attach([$pupr->id, $dishub->id]);

        $this->assertCount(2, $this->category->fresh()->dinasUnits);
    }

    public function test_dinas_unit_maps_to_many_categories(): void
    {
        $dinas = DinasUnit::create(['name' => 'Dinas Multi', 'is_active' => true]);
        $catB = ComplaintCategory::create([
            'name' => 'Kategori B', 'slug' => 'kategori-b', 'is_active' => true, 'sort_order' => 2,
        ]);

        $dinas->categories()->attach([$this->category->id, $catB->id]);

        $this->assertCount(2, $dinas->fresh()->categories);
    }

    public function test_pivot_rejects_duplicate_mapping(): void
    {
        $dinas = DinasUnit::create(['name' => 'Dinas Unik', 'is_active' => true]);
        $this->category->dinasUnits()->attach($dinas->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->category->dinasUnits()->attach($dinas->id);
    }

    // =========================================================================
    // PRIVACY / IDOR
    // =========================================================================

    public function test_guest_cannot_access_complaint_surface(): void
    {
        $complaint = $this->makeComplaint($this->citizen);

        $this->get(route('citizen.complaint.show', $complaint))->assertRedirect(route('login'));
        $this->get(route('citizen.dashboard'))->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_another_citizens_complaint(): void
    {
        $other = $this->makeComplaint($this->otherCitizen);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $other))
            ->assertForbidden();
    }

    public function test_citizen_cannot_download_another_citizens_attachment(): void
    {
        Storage::fake('private');

        $other = $this->makeComplaint($this->otherCitizen);
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $other->id,
            'disk' => 'private',
            'path' => 'complaints/' . $other->id . '/secret.jpg',
            'original_name' => 'secret.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.attachment', [$other, $attachment]))
            ->assertForbidden();
    }

    public function test_internal_notes_are_hidden_from_citizen(): void
    {
        $complaint = $this->makeComplaint($this->citizen);
        \App\Models\ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->otherCitizen->id,
            'visibility'   => 'internal',
            'body'         => 'CATATAN INTERNAL 5B RAHASIA',
        ]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('CATATAN INTERNAL 5B RAHASIA');
    }
}
