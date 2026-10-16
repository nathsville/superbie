@php
    use App\Enums\UserRole;

    $title = 'Profil Saya';

    $roleLbl  = ($r = UserRole::tryFrom((string) $user->role)) ? $r->label() : ucwords(str_replace('_', ' ', (string) $user->role));
@endphp

{{--
    Masyarakat — Profil Saya.

    The citizen's OWN real record. A citizen may self-edit name, phone number,
    address, their photo, and their password. Email and NIK are IDENTITY fields
    shown READ-ONLY: NIK is immutable after registration (FINAL business rule)
    and email is the login credential. Nothing on this page is fabricated.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        @include('citizen.partials.nav', ['currentRoute' => $route])
    </x-slot:navigation>

    <x-slot:header>Profil Akun</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Kelola data diri dan keamanan akun Anda</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        <form
            action="{{ route('citizen.profile.update') }}"
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

                        <p class="text-sm font-semibold text-ink-faint mt-1">{{ $roleLbl }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink-soft">
                            <span class="inline-flex items-center gap-1.5 min-w-0">
                                <svg class="w-4 h-4 shrink-0 text-ink-ghost" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="truncate">{{ $user->email }}</span>
                            </span>

                            @if ($user->address)
                                <span class="inline-flex items-center gap-1.5 min-w-0">
                                    <svg class="w-4 h-4 shrink-0 text-ink-ghost" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="truncate">{{ $user->address }}</span>
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-ink-ghost mt-2">
                            Bergabung sejak {{ $user->created_at?->translatedFormat('d F Y') ?? '—' }}
                        </p>

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
                        hint="Email adalah kredensial login dan tidak dapat diubah di sini."
                    />

                    <x-profile.field
                        label="Nomor HP"
                        name="phone_number"
                        :value="$user->phone_number"
                        placeholder="08xxxxxxxxxx"
                        hint="Format lokal (08…) atau internasional (+62…)."
                    />

                    <x-profile.field
                        label="NIK"
                        :value="$user->nik"
                        hint="NIK ditampilkan penuh dan tidak dapat diubah setelah akun dibuat."
                    />

                    <x-profile.field label="Peran" :value="$roleLbl" />

                    <x-profile.field label="Status Akun" :value="$user->is_active ? 'Aktif' : 'Tidak Aktif'" />
                </div>
            </div>

            {{-- ── Address ───────────────────────────────────────────────────── --}}
            <div class="soft-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-6 py-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">Alamat</h2>
                        <p class="text-xs text-ink-soft mt-0.5">Alamat tempat tinggal Anda saat ini.</p>
                    </div>
                    <x-profile.edit-button label="Edit" />
                </div>

                <div class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5">
                    <x-profile.field
                        label="Alamat"
                        name="address"
                        :value="$user->address"
                        :textarea="true"
                        placeholder="Alamat lengkap Anda"
                        span="3"
                    />

                    <x-profile.field
                        label="Bergabung Sejak"
                        :value="$user->created_at?->translatedFormat('d F Y')"
                        span="2"
                    />

                    <x-profile.field
                        label="Terakhir Diperbarui"
                        :value="$user->updated_at?->translatedFormat('d F Y, H:i')"
                    />
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
