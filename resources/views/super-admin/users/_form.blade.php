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
<form method="POST" action="{{ $action }}" class="space-y-5 bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter"
      x-data="{ role: '{{ $selectedRole }}' }">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label for="name" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Nama <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" required maxlength="120"
            value="{{ old('name', $user->name ?? '') }}"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Email <span class="text-red-500">*</span></label>
        <input id="email" name="email" type="email" required maxlength="255"
            value="{{ old('email', $user->email ?? '') }}"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('email') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    @unless ($isEdit)
        <div>
            <label for="password" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Kata Sandi <span class="text-red-500">*</span></label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('password') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
            @error('password')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Konfirmasi Kata Sandi <span class="text-red-500">*</span></label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="w-full px-4 py-2.5 rounded-xl border border-[#E2E8F0] text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        </div>
    @endunless

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="role" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Role <span class="text-red-500">*</span></label>
            <select id="role" name="role" required x-model="role"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('role') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected($selectedRole === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                @endforeach
            </select>
            @error('role')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="is_active" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Status</label>
            <select id="is_active" name="is_active"
                class="w-full px-4 py-2.5 rounded-xl border border-[#E2E8F0] text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
                <option value="1" @selected((string) old('is_active', $user->is_active ?? true) === '1')>Aktif</option>
                <option value="0" @selected((string) old('is_active', $user->is_active ?? true) === '0')>Nonaktif</option>
            </select>
            <p class="mt-1 text-[11px] text-[#94A3B8]">Pengguna nonaktif tidak dapat login.</p>
        </div>
    </div>

    {{-- Dinas/Unit scope — required for Operator accounts (Prompt 15 — BDR-1 = 1a).
         Each Operator belongs to EXACTLY ONE Dinas/Unit; an Operator without a
         unit is denied (sees nothing). Hidden for non-operator roles. --}}
    <div x-show="role === 'operator'" x-cloak>
        <label for="dinas_unit_id" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Dinas/Unit <span class="text-red-500">*</span></label>
        <select id="dinas_unit_id" name="dinas_unit_id"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('dinas_unit_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
            <option value="">— Pilih Dinas/Unit —</option>
            @foreach ($dinasUnits as $dinasUnit)
                <option value="{{ $dinasUnit->id }}" @selected((string) $selectedDinasUnit === (string) $dinasUnit->id)>
                    {{ $dinasUnit->name }}{{ $dinasUnit->is_active ? '' : ' (nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('dinas_unit_id')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-[11px] text-[#94A3B8]">Operator hanya dapat mengelola laporan pada Dinas/Unit ini.</p>
    </div>

    @if ($isEdit)
        <p class="text-[11px] text-[#94A3B8]">
            Kata sandi tidak diubah melalui formulir ini. Pengelolaan kata sandi oleh administrator belum ditetapkan sebagai alur resmi.
        </p>
    @endif

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' }}
        </button>
        <a href="{{ route('super-admin.users.index') }}" class="text-sm text-[#64748B] hover:underline">Batal</a>
    </div>
</form>
