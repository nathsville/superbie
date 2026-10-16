@php
    $user = $user ?? null;
    $isEdit = $user !== null;
    $roleLabels = [
        'masyarakat'  => 'Masyarakat',
        'operator'    => 'Operator',
        'admin'       => 'Admin',
        'super_admin' => 'Super Admin',
    ];
    $dinasUnits = $dinasUnits ?? collect();
    $selectedRole = old('role', $user->role ?? 'masyarakat');
    $selectedDinasUnit = old('dinas_unit_id', $user->dinas_unit_id ?? '');
@endphp
@php
    $action = $isEdit ? route('super-admin.users.update', $user) : route('super-admin.users.store');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-6 bg-white rounded-2xl border border-line p-6 sm:p-8 shadow-sm card-enter"
      x-data="{ role: '{{ $selectedRole }}' }">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ── 2-column grid ──────────────────────────────────────────────────────
         Row 1: Nama Lengkap | Alamat Email
         Row 2: Role / Peran | Status Akun                                          --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
        <div>
            <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="120"
                value="{{ old('name', $user->name ?? '') }}"
                placeholder="Nama lengkap pengguna"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }} text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-ink mb-1.5">Alamat Email <span class="text-red-500">*</span></label>
            <input id="email" name="email" type="email" required maxlength="255"
                value="{{ old('email', $user->email ?? '') }}"
                placeholder="nama@contoh.com"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('email') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }} text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="role" class="block text-sm font-semibold text-ink mb-1.5">Role / Peran <span class="text-red-500">*</span></label>
            <select id="role" name="role" required x-model="role"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('role') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }} text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected($selectedRole === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                @endforeach
            </select>
            @error('role')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="is_active" class="block text-sm font-semibold text-ink mb-1.5">Status Akun</label>
            <select id="is_active" name="is_active"
                class="w-full px-4 py-2.5 rounded-xl border border-line text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
                <option value="1" @selected((string) old('is_active', $user->is_active ?? true) === '1')>Aktif</option>
                <option value="0" @selected((string) old('is_active', $user->is_active ?? true) === '0')>Nonaktif</option>
            </select>
            <p class="mt-1 text-[11px] text-ink-ghost">Pengguna nonaktif tidak dapat login.</p>
        </div>
    </div>

    {{-- ── Password fields (create only) ─────────────────────────────────────── --}}
    @unless ($isEdit)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <div>
                <label for="password" class="block text-sm font-semibold text-ink mb-1.5">Kata Sandi <span class="text-red-500">*</span></label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('password') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }} text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
                @error('password')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-ink mb-1.5">Konfirmasi Kata Sandi <span class="text-red-500">*</span></label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    placeholder="Ulangi kata sandi"
                    class="w-full px-4 py-2.5 rounded-xl border border-line text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
            </div>
        </div>
    @endunless

    {{-- ── Dinas/Unit scope — required for Operator accounts (Prompt 15 — BDR-1 = 1a).
         Each Operator belongs to EXACTLY ONE Dinas/Unit; an Operator without a
         unit is denied (sees nothing). Hidden for non-operator roles. ─────────── --}}
    <div x-show="role === 'operator'" x-cloak class="max-w-md">
        <label for="dinas_unit_id" class="block text-sm font-semibold text-ink mb-1.5">Dinas/Unit <span class="text-red-500">*</span></label>
        <select id="dinas_unit_id" name="dinas_unit_id"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('dinas_unit_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }} text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated">
            <option value="">— Pilih Dinas/Unit —</option>
            @foreach ($dinasUnits as $dinasUnit)
                <option value="{{ $dinasUnit->id }}" @selected((string) $selectedDinasUnit === (string) $dinasUnit->id)>
                    {{ $dinasUnit->name }}{{ $dinasUnit->is_active ? '' : ' (nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('dinas_unit_id')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-[11px] text-ink-ghost">Operator hanya dapat mengelola laporan pada Dinas/Unit ini.</p>
    </div>

    {{-- ── Password help — clean info box below the grid ────────────────────── --}}
    @if ($isEdit)
        <div class="flex items-start gap-3 rounded-xl bg-primary-50 border border-primary-100 px-4 py-3.5">
            <span class="w-8 h-8 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-primary-900">Kata Sandi</p>
                <p class="text-xs text-ink-soft mt-0.5 leading-relaxed">
                    Kata sandi tidak diubah melalui formulir ini. Pengelolaan kata sandi oleh administrator belum ditetapkan sebagai alur resmi.
                </p>
            </div>
        </div>
    @endif

    {{-- ── Action bar (bottom-right) ────────────────────────────────────────── --}}
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-line">
        <a href="{{ route('super-admin.users.index') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-ink-soft bg-white hover:bg-surface-sunken border border-line-strong rounded-xl ui-animated">
            Batal
        </a>
        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' }}
        </button>
    </div>
</form>
