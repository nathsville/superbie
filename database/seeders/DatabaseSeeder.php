<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatusHistory;
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

        $petugas1 = User::firstOrCreate(
            ['email' => 'petugas1@superbie.local'],
            [
                'name'      => 'Petugas Lapangan A',
                'password'  => Hash::make('password123'),
                'role'      => 'petugas',
                'is_active' => true,
            ]
        );

        $petugas2 = User::firstOrCreate(
            ['email' => 'petugas2@superbie.local'],
            [
                'name'      => 'Petugas Lapangan B',
                'password'  => Hash::make('password123'),
                'role'      => 'petugas',
                'is_active' => true,
            ]
        );

        $citizen1 = User::firstOrCreate(
            ['email' => 'warga1@superbie.local'],
            [
                'name'      => 'Ahmad Warga',
                'password'  => Hash::make('password123'),
                'role'      => 'masyarakat',
                'is_active' => true,
            ]
        );

        $citizen2 = User::firstOrCreate(
            ['email' => 'warga2@superbie.local'],
            [
                'name'      => 'Siti Warga',
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

        // ─── 3. Sample Complaints for Development ─────────────────────────────

        $sampleComplaints = [
            [
                'reference_code' => 'LPW-' . strtoupper(Str::random(8)),
                'reporter_id'    => $citizen1->id,
                'category_id'    => $seededCategories[0]->id,
                'assigned_to'    => $petugas1->id,
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
                'assigned_to'    => $petugas2->id,
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
                'assigned_to'    => $petugas1->id,
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
                    'changed_by'   => $petugas1->id,
                    'note'         => 'Petugas sedang menindaklanjuti ke lokasi.',
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
