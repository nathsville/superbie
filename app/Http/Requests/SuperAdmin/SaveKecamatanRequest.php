<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Kecamatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKecamatanRequest extends FormRequest
{
    /**
     * Only Super Admin may manage Kecamatan master data.
     * (Route middleware enforces this too; authorization is never UI-only.)
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        $kecamatan = $this->route('kecamatan');
        $kecamatanId = $kecamatan instanceof Kecamatan ? $kecamatan->id : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            // Optional code (BPS area code). Unique when present. Format is NOT
            // invented here — it is supplied with the official data.
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('kecamatan', 'code')->ignore($kecamatanId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama Kecamatan wajib diisi.',
            'name.max' => 'Nama Kecamatan maksimal 150 karakter.',
            'code.max' => 'Kode maksimal 50 karakter.',
            'code.unique' => 'Kode Kecamatan sudah digunakan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
        ]);
    }
}
