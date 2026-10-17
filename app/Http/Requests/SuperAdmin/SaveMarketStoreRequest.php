<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super Admin — Retail store validation (PHASE S / SIPHP module).
 *
 * Photos are PUBLIC by design (retail directory, D-S7): MIME allowlist and
 * max size come from `config/siphp.php` (technical bounds, not business
 * rules). The original filename is never used as a path.
 */
class SaveMarketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        $maxKb = (int) config('siphp.store_photo.max_kilobytes');
        $mimes = implode(',', (array) config('siphp.store_photo.mimes'));

        return [
            'market_id'     => ['nullable', 'integer', 'exists:markets,id'],
            'name'          => ['required', 'string', 'max:150'],
            'type'          => ['nullable', 'string', 'max:100'],
            'address'       => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'opening_hours' => ['nullable', 'string', 'max:100'],
            'photo'         => ['nullable', 'image', 'mimes:' . $mimes, 'max:' . $maxKb],
            'remove_photo'  => ['nullable', 'boolean'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'market_id.exists'   => 'Pasar yang dipilih tidak ditemukan.',
            'name.required'      => 'Nama toko wajib diisi.',
            'name.max'           => 'Nama toko maksimal 150 karakter.',
            'type.max'           => 'Jenis toko maksimal 100 karakter.',
            'address.max'        => 'Alamat maksimal 255 karakter.',
            'phone.max'          => 'Nomor telepon maksimal 30 karakter.',
            'opening_hours.max'  => 'Jam buka maksimal 100 karakter.',
            'photo.image'        => 'Foto harus berupa gambar.',
            'photo.mimes'        => 'Foto harus berformat JPG atau PNG.',
            'photo.max'          => 'Ukuran foto melebihi batas yang diizinkan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'          => trim((string) $this->input('name')),
            'type'          => $this->filled('type') ? trim((string) $this->input('type')) : null,
            'address'       => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'phone'         => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'opening_hours' => $this->filled('opening_hours') ? trim((string) $this->input('opening_hours')) : null,
            'remove_photo'  => $this->boolean('remove_photo'),
            'is_active'     => $this->boolean('is_active'),
        ]);
    }
}
