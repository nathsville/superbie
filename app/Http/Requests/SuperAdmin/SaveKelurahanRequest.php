<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Kelurahan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKelurahanRequest extends FormRequest
{
    /**
     * Only Super Admin may manage Kelurahan master data.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        $kelurahan = $this->route('kelurahan');
        $kelurahanId = $kelurahan instanceof Kelurahan ? $kelurahan->id : null;

        return [
            // A Kelurahan must belong to an existing Kecamatan.
            'kecamatan_id' => ['required', 'integer', 'exists:kecamatan,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('kelurahan', 'code')->ignore($kelurahanId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'kecamatan_id.required' => 'Kecamatan induk wajib dipilih.',
            'kecamatan_id.exists' => 'Kecamatan yang dipilih tidak ditemukan.',
            'name.required' => 'Nama Kelurahan wajib diisi.',
            'name.max' => 'Nama Kelurahan maksimal 150 karakter.',
            'code.max' => 'Kode maksimal 50 karakter.',
            'code.unique' => 'Kode Kelurahan sudah digunakan.',
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
