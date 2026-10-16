@php
    use App\Enums\UserRole;

    $title = 'Profil Saya';

    $staffRole    = $user->role;
    $staffRoleLbl = ($r = UserRole::tryFrom((string) $staffRole)) ? $r->label() : ucwords(str_replace('_', ' ', (string) $staffRole));
    $currentRoute = request()->route()?->getName() ?? '';

    // Dinas/Unit is meaningful for operators only (Prompt 15 — one Operator →
    // exactly one Dinas/Unit). Resolved by the controller; NULL for other roles.
    $unitName = $dinasUnit?->name;
    $unitCode = $dinasUnit?->code;
@endphp

{{--
    Staff — Profil Saya.

    The account's OWN real record. A staff member may self-edit their own
    non-privileged contact fields (name, phone number), their photo, and their
    password. Privileged fields — role, Dinas/Unit, email and NIK — are shown
    READ-ONLY and are NEVER editable here: they remain Super-Admin-only via User
    Management (Prompt 9/21) and are additionally enforced at the model layer
    (User::booted). Nothing on this page is fabricated.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @if ($staffRole === 'super_admin')
            @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
        @elseif ($staffRole === 'admin')
            <x-dashboard.nav-rail-link :href="route('admin.dashboard')" label="Monitoring">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
            <x-dashboard.nav-rail-link :href="route('admin.complaints')" label="Monitoring Laporan">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
        @else
            <x-dashboard.nav-rail-link :href="route('operator.dashboard')" label="Dashboard" :active="$currentRoute === 'operator.dashboard'">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
            <x-dashboard.nav-rail-link :href="route('operator.complaint.index')" label="Kelola Laporan" :active="str_starts_with($currentRoute, 'operator.complaint')">
                <x-slot:icon>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </x-slot:icon>
            </x-dashboard.nav-rail-link>
        @endif
    </x-slot:navigation>

    <x-slot:header>Profil Akun</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Kelola data diri dan keamanan akun Anda</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        <form
            action="{{ route('staff.profile.update') }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }}, saving: false, photoName: '' }"
            @submit="saving = true"
            class="space-y-6 card-enter"
        >
            @csrf
            @method('PATCH')

            {{-- ── Header card: photo + identity ─────────────────────────────── --}}
            <div class="soft-card p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                    <x-profile.avatar :user="$user" :editable="true" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-bold text-ink break-words">{{ $user->name }}</h1>
                            @if ($user->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500" aria-hidden="true"></span>
                                    Tidak Aktif
                                </span>
                            @endif
                        </div>

                        <p class="text-sm font-semibold text-ink-faint mt-1">{{ $staffRoleLbl }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink-soft">
                            <span class="inline-flex items-center gap-1.5 min-w-0">
                                <svg class="w-4 h-4 shrink-0 text-ink-ghost" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="truncate">{{ $user->email }}</span>
                            </span>

                            @if ($unitName)
                                <span class="inline-flex items-center gap-1.5 min-w-0">
                                    <svg class="w-4 h-4 shrink-0 text-ink-ghost" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="truncate">{{ $unitName }}</span>
                                </span>
                            @endif
                        </div>

                        {{-- Live feedback for a chosen-but-not-yet-saved photo. --}}
                        <p class="text-xs text-warning mt-2" x-show="photoName" x-cloak>
                            Foto baru dipilih: <span class="font-semibold" x-text="photoName"></span> — simpan untuk menerapkan.
                        </p>

                        {{-- Photo validation feedback (always visible — the camera
                             badge is a real file input, not gated by edit mode). --}}
                        @error('avatar')
                            <p class="text-xs text-red-600 mt-2 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- ── Personal information ──────────────────────────────────────── --}}
            <div class="soft-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-6 py-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">Informasi Pribadi</h2>
                        <p class="text-xs text-ink-soft mt-0.5">Data kontak yang dapat Anda perbarui sendiri.</p>
                    </div>
                    <x-profile.edit-button label="Edit" />
                </div>

                <div class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5">
                    <x-profile.field
                        label="Nama Lengkap"
                        name="name"
                        :value="$user->name"
                        placeholder="Nama lengkap Anda"
                    />

                    <x-profile.field
                        label="Alamat Email"
                        :value="$user->email"
                        hint="Email adalah kredensial login dan hanya dapat diubah oleh Super Admin."
                    />

                    <x-profile.field
                        label="Nomor HP"
                        name="phone_number"
                        :value="$user->phone_number"
                        placeholder="08xxxxxxxxxx"
                        hint="Format lokal (08…) atau internasional (+62…)."
                    />

                    <x-profile.field label="Peran" :value="$staffRoleLbl" />

                    <x-profile.field label="Status Akun" :value="$user->is_active ? 'Aktif' : 'Tidak Aktif'" />

                    <x-profile.field
                        label="Bergabung Sejak"
                        :value="$user->created_at?->translatedFormat('d F Y')"
                    />
                </div>
            </div>

            {{-- ── Organisation detail (read-only) ──────────────────────────── --}}
            <div class="soft-card overflow-hidden">
                <div class="flex items-start justify-between gap-3 px-6 py-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">Detail Organisasi</h2>
                        <p class="text-xs text-ink-soft mt-0.5">
                            @if ($user->isOperator())
                                Penempatan dan unit kerja Anda.
                            @else
                                Ringkasan keanggotaan akun Anda.
                            @endif
                        </p>
                    </div>
                    {{-- No edit affordance: these values are Super-Admin-only and
                         must never look editable from here. --}}
                </div>

                <div class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5">
                    @if ($user->isOperator())
                        <x-profile.field label="Dinas/Unit" :value="$unitName ?? 'Belum ditetapkan'" />
                        <x-profile.field label="Kode Unit" :value="$unitCode" />
                    @endif

                    <x-profile.field
                        label="Terakhir Diperbarui"
                        :value="$user->updated_at?->translatedFormat('d F Y, H:i')"
                        :span="$user->isOperator() ? null : 3"
                    />
                </div>

                <div class="px-6 pb-6">
                    <div class="flex items-start gap-3 rounded-xl bg-page border border-line px-4 py-3">
                        <span class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center shrink-0" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <p class="text-xs text-ink-soft leading-relaxed">
                            @if ($user->isOperator())
                                Penempatan, peran, dan unit kerja dikelola oleh Super Admin melalui menu Kelola Pengguna dan tidak dapat diubah dari halaman ini.
                            @else
                                Peran dan data kepegawaian dikelola oleh Super Admin melalui menu Kelola Pengguna dan tidak dapat diubah dari halaman ini.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- ── Security & password ───────────────────────────────────────── --}}
            <div class="soft-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-6 py-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">Keamanan &amp; Kata Sandi</h2>
                        <p class="text-xs text-ink-soft mt-0.5">Kosongkan bila Anda tidak ingin mengubah kata sandi.</p>
                    </div>
                    <x-profile.edit-button label="Edit" />
                </div>

                <div class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5">
                    <x-profile.field
                        label="Kata Sandi Saat Ini"
                        name="current_password"
                        type="password"
                        :prefill="false"
                        placeholder="Kata sandi saat ini"
                        hint="Wajib diisi hanya jika Anda menetapkan kata sandi baru."
                    />

                    <x-profile.field
                        label="Kata Sandi Baru"
                        name="password"
                        type="password"
                        :prefill="false"
                        placeholder="Minimal 8 karakter"
                        hint="Gunakan kombinasi huruf, angka, dan simbol."
                    />

                    <x-profile.field
                        label="Konfirmasi Kata Sandi Baru"
                        name="password_confirmation"
                        type="password"
                        :prefill="false"
                        placeholder="Ulangi kata sandi baru"
                        hint="Harus sama persis dengan kata sandi baru."
                    />
                </div>
            </div>

            {{-- ── Save / cancel bar (edit mode only) ────────────────────────── --}}
            <div
                x-show="editing"
                x-cloak
                class="sticky bottom-4 soft-card px-5 sm:px-6 py-4 flex items-center justify-between gap-4"
            >
                <p class="text-xs text-ink-soft hidden sm:block">
                    Perubahan diterapkan setelah Anda menekan <span class="font-semibold text-ink">Simpan Perubahan</span>.
                </p>

                <div class="flex items-center gap-2.5 ml-auto">
                    <button
                        type="button"
                        @click="editing = false; photoName = ''"
                        class="text-sm font-semibold text-ink-soft hover:text-ink px-4 py-2.5 rounded-xl border border-line-strong bg-white hover:bg-surface-sunken ui-animated"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        :disabled="saving"
                        class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-6 py-2.5 rounded-xl ui-animated shadow-sm hover:shadow active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg x-show="saving" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span x-text="saving ? 'Menyimpan…' : 'Simpan Perubahan'">Simpan Perubahan</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
