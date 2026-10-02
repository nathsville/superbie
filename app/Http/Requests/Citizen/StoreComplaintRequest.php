<?php

namespace App\Http\Requests\Citizen;

use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'masyarakat';
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                'exists:complaint_categories,id',
                function ($attribute, $value, $fail) {
                    $category = ComplaintCategory::find($value);
                    if (!$category || !$category->is_active) {
                        $fail('Kategori yang dipilih tidak aktif atau tidak ditemukan.');
                    }
                },
            ],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // Max 5MB per file
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Pilih kategori laporan.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'title.required' => 'Judul laporan wajib diisi.',
            'title.min' => 'Judul laporan minimal 5 karakter.',
            'title.max' => 'Judul laporan maksimal 255 karakter.',
            'description.required' => 'Deskripsi laporan wajib diisi.',
            'description.min' => 'Deskripsi laporan minimal 10 karakter.',
            'location_text.max' => 'Lokasi kejadian maksimal 255 karakter.',
            'attachments.max' => 'Maksimal 3 lampiran berkas yang dapat diunggah.',
            'attachments.*.mimes' => 'Format berkas lampiran harus berupa JPG, PNG, atau PDF.',
            'attachments.*.max' => 'Ukuran berkas lampiran tidak boleh melebihi 5 MB per berkas.',
        ];
    }

    /**
     * Additional validation for daily report limit.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $limit = AppSetting::get('daily_report_limit');
            if ($limit && is_numeric($limit) && (int) $limit > 0) {
                $todayCount = Complaint::where('reporter_id', $this->user()->id)
                    ->whereDate('submitted_at', today())
                    ->count();

                if ($todayCount >= (int) $limit) {
                    $validator->errors()->add(
                        'daily_limit',
                        'Batas pengiriman laporan harian telah tercapai. Anda hanya dapat mengirim maksimal ' . $limit . ' laporan per hari.'
                    );
                }
            }
        });
    }
}
