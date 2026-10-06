<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for local development.
     * All credentials are for development only — NEVER use in production.
     */
    public function run(): void
    {
        // ─── 1. Users for each required role ─────────────────────────────────

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@superbie.local'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('password123'),
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@superbie.local'],
            [
                'name'      => 'Admin Monitoring',
                'password'  => Hash::make('password123'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        $operator = User::firstOrCreate(
            ['email' => 'operator@superbie.local'],
            [
                'name'      => 'Operator Utama',
                'password'  => Hash::make('password123'),
                'role'      => 'operator',
                'is_active' => true,
            ]
        );

        $citizen1 = User::firstOrCreate(
            ['email' => 'warga1@superbie.local'],
            [
                'name'      => 'Ahmad Warga',
                // Development fixture identity — NOT official/real data.
                // Real NIK/phone are supplied by the community at registration.
                'nik'          => '7373000000000001',
                'phone_number' => '081100000001',
                'address'      => 'Alamat contoh pengembangan, Parepare',
                'password'  => Hash::make('password123'),
                'role'      => 'masyarakat',
                'is_active' => true,
            ]
        );

        $citizen2 = User::firstOrCreate(
            ['email' => 'warga2@superbie.local'],
            [
                'name'      => 'Siti Warga',
                // Development fixture identity — NOT official/real data.
                'nik'          => '7373000000000002',
                'phone_number' => '081100000002',
                'address'      => 'Alamat contoh pengembangan, Parepare',
                'password'  => Hash::make('password123'),
                'role'      => 'masyarakat',
                'is_active' => true,
            ]
        );

        // ─── 2. Categories with Dinas/Unit mapping (Development fixtures) ───────
        // NOTE: Official categories and dinas mapping: TODO: Define requirement
        // These are demo fixtures clearly marked as development fixtures per rules.md §19

        $categories = [
            [
                'name'        => 'Infrastruktur Jalan & Jembatan',
                'slug'        => 'infrastruktur-jalan-jembatan',
                'description' => 'Keluhan terkait jalan berlubang, rusak, jembatan rusak, atau trotoar',
                'dinas_name'  => 'Dinas Pekerjaan Umum dan Penataan Ruang (PUPR)',
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Penerangan Jalan Umum (PJU)',
                'slug'        => 'penerangan-jalan-umum',
                'description' => 'Lampu jalan mati, rusak, atau area yang membutuhkan penerangan',
                'dinas_name'  => 'Dinas Perhubungan (Dishub)',
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Kebersihan & Pengelolaan Sampah',
                'slug'        => 'kebersihan-pengelolaan-sampah',
                'description' => 'Tumpukan sampah, tempat pembuangan liar, jadwal pengangkutan sampah',
                'dinas_name'  => 'Dinas Lingkungan Hidup (DLH)',
                'is_active'   => true,
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Saluran Air & Drainase',
                'slug'        => 'saluran-air-drainase',
                'description' => 'Saluran air tersumbat, genangan air, atau banjir lokal',
                'dinas_name'  => 'Dinas Pekerjaan Umum dan Penataan Ruang (PUPR)',
                'is_active'   => true,
                'sort_order'  => 4,
            ],
            [
                'name'        => 'Pelayanan Publik & Administrasi',
                'slug'        => 'pelayanan-publik-administrasi',
                'description' => 'Keluhan terkait pelayanan kependudukan, perizinan, atau administrasi',
                'dinas_name'  => 'Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil)',
                'is_active'   => true,
                'sort_order'  => 5,
            ],
        ];

        $seededCategories = [];
        foreach ($categories as $cat) {
            $seededCategories[] = ComplaintCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }

        // ─── 2b. Dinas/Unit samples + Category mapping (DEVELOPMENT SAMPLES) ────
        // These are MINIMAL development/demo samples only — NOT an official
        // government list. Super Admin manages the authoritative data via the
        // dashboard (Prompt 5C). Names are illustrative.

        $dinasSamples = [
            ['name' => 'Dinas Pekerjaan Umum dan Penataan Ruang (PUPR)', 'code' => 'PUPR', 'sort_order' => 1],
            ['name' => 'Dinas Perhubungan (Dishub)',                     'code' => 'DISHUB', 'sort_order' => 2],
            ['name' => 'Dinas Lingkungan Hidup (DLH)',                   'code' => 'DLH', 'sort_order' => 3],
            ['name' => 'Dinas Kependudukan dan Pencatatan Sipil',        'code' => 'DISDUKCAPIL', 'sort_order' => 4],
        ];

        $seededDinas = [];
        foreach ($dinasSamples as $d) {
            $seededDinas[$d['code']] = DinasUnit::firstOrCreate(
                ['code' => $d['code']],
                [
                    'name'        => $d['name'],
                    'code'        => $d['code'],
                    'description' => null,
                    'is_active'   => true,
                    'sort_order'  => $d['sort_order'],
                ]
            );
        }

        // Category ↔ Dinas/Unit mapping (development samples, many-to-many).
        // slug => [dinas codes]
        $categoryMapping = [
            'infrastruktur-jalan-jembatan' => ['PUPR'],
            'penerangan-jalan-umum'        => ['DISHUB'],
            'kebersihan-pengelolaan-sampah' => ['DLH'],
            'saluran-air-drainase'         => ['PUPR'],
            'pelayanan-publik-administrasi' => ['DISDUKCAPIL'],
        ];

        foreach ($seededCategories as $category) {
            $codes = $categoryMapping[$category->slug] ?? [];
            $ids = collect($codes)
                ->map(fn ($code) => $seededDinas[$code]->id ?? null)
                ->filter()
                ->all();

            if (! empty($ids)) {
                $category->dinasUnits()->syncWithoutDetaching($ids);
            }
        }

        // Assign the seeded Operator a Dinas/Unit scope (Prompt 15 — BDR-1 = 1a).
        // An Operator MUST belong to exactly one unit; otherwise it is denied all
        // complaints. This is a DEVELOPMENT FIXTURE assignment (deterministic by
        // `code`), NOT a production business rule. Only set when still unassigned
        // so re-seeding never overrides a deliberate Super Admin assignment.
        if ($operator->dinas_unit_id === null && isset($seededDinas['PUPR'])) {
            $operator->forceFill(['dinas_unit_id' => $seededDinas['PUPR']->id])->save();
        }

        // ─── 3. Sample Complaints for Development ─────────────────────────────

        $sampleComplaints = [
            [
                'reference_code' => 'LPW-' . strtoupper(Str::random(8)),
                'reporter_id'    => $citizen1->id,
                'category_id'    => $seededCategories[0]->id,
                'assigned_to'    => $operator->id,
                'title'          => 'Jalan berlubang di Jl. Bau Massepe',
                'description'    => 'Terdapat lubang cukup besar di dekat pertigaan jalan yang membahayakan pengendara sepeda motor, terutama saat malam hari.',
                'location_text'  => 'Jl. Bau Massepe, depan Toko Makmur',
                'status'         => 'in_progress',
                'submitted_at'   => now()->subDays(3),
            ],
            [
                'reference_code' => 'LPW-' . strtoupper(Str::random(8)),
                'reporter_id'    => $citizen1->id,
                'category_id'    => $seededCategories[1]->id,
                'assigned_to'    => $operator->id,
                'title'          => 'Lampu jalan mati di Jl. Mattirotasi',
                'description'    => 'Dua tiang lampu PJU padam sejak 4 hari yang lalu, membuat jalan gelap dan rawan kejahatan.',
                'location_text'  => 'Jl. Mattirotasi No. 45',
                'status'         => 'under_review',
                'submitted_at'   => now()->subDays(1),
            ],
            [
                'reference_code' => 'LPW-' . strtoupper(Str::random(8)),
                'reporter_id'    => $citizen1->id,
                'category_id'    => $seededCategories[2]->id,
                'assigned_to'    => $operator->id,
                'title'          => 'Sampah menumpuk di TPS Pasar Lakessi',
                'description'    => 'Kontainer sampah sudah penuh dan meluap ke badan jalan. Mohon segera diangkut.',
                'location_text'  => 'Pasar Lakessi, pintu utara',
                'status'         => 'resolved',
                'submitted_at'   => now()->subDays(7),
                'resolved_at'    => now()->subDays(2),
            ],
            [
                'reference_code' => 'LPW-' . strtoupper(Str::random(8)),
                'reporter_id'    => $citizen2->id,
                'category_id'    => $seededCategories[3]->id,
                'assigned_to'    => null, // unassigned
                'title'          => 'Drainase tersumbat menyebabkan genangan',
                'description'    => 'Hujan deras kemarin menyebabkan genangan air masuk ke teras rumah warga karena selokan tersumbat sedimen.',
                'location_text'  => 'Jl. Lasinrang, Kelurahan Ujung Sabbang',
                'status'         => 'submitted',
                'submitted_at'   => now()->subHours(5),
            ],
        ];

        foreach ($sampleComplaints as $cData) {
            // Derive the ACTUAL destination from the complaint's category mapping
            // (development sample data only).
            $destDinas = null;
            $category = ComplaintCategory::find($cData['category_id']);
            if ($category) {
                $destDinas = $category->dinasUnits()->first();
            }

            if ($destDinas) {
                $cData['dinas_unit_id'] = $destDinas->id;
            }

            $complaint = Complaint::firstOrCreate(
                ['reference_code' => $cData['reference_code']],
                $cData
            );

            // Initial status history
            ComplaintStatusHistory::firstOrCreate(
                ['complaint_id' => $complaint->id, 'to_status' => 'submitted'],
                [
                    'from_status' => null,
                    'changed_by'  => $complaint->reporter_id,
                    'note'        => 'Laporan berhasil diajukan oleh masyarakat.',
                    'created_at'  => $complaint->submitted_at,
                ]
            );

            // Additional history if processed
            if ($complaint->status->value === 'in_progress') {
                ComplaintStatusHistory::create([
                    'complaint_id' => $complaint->id,
                    'from_status'  => 'submitted',
                    'to_status'    => 'under_review',
                    'changed_by'   => $operator->id,
                    'note'         => 'Laporan ditinjau oleh operator.',
                    'created_at'   => $complaint->submitted_at->addHours(2),
                ]);
                ComplaintStatusHistory::create([
                    'complaint_id' => $complaint->id,
                    'from_status'  => 'under_review',
                    'to_status'    => 'in_progress',
                    'changed_by'   => $operator->id,
                    'note'         => 'Operator sedang menindaklanjuti laporan.',
                    'created_at'   => $complaint->submitted_at->addHours(6),
                ]);
            }
        }

        // ─── 4. Initial Audit Logs ────────────────────────────────────────────

        AuditLog::create([
            'actor_id'     => $superAdmin->id,
            'action'       => 'system.seeded',
            'subject_type' => 'System',
            'subject_id'   => null,
            'metadata'     => ['environment' => 'local'],
            'ip_address'   => '127.0.0.1',
            'user_agent'   => 'Seeder/CLI',
            'created_at'   => now(),
        ]);
    }
}
