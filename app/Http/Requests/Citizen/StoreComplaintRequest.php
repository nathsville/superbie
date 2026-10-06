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
            // FINAL attachment rule: optional, max 10 files, 20 MB/file,
            // formats jpg/jpeg/png/pdf/mp4. No malware scanning required.
            'attachments' => ['nullable', 'array', 'max:' . config('business_rules.attachment.max_files')],
            'attachments.*' => [
                'file',
                'mimes:' . implode(',', config('business_rules.attachment.mimes')),
                'max:' . config('business_rules.attachment.max_kilobytes'),
            ],
        ];
    }

    public function messages(): array
    {
        $maxFiles = config('business_rules.attachment.max_files');
        $maxMb = (int) (config('business_rules.attachment.max_kilobytes') / 1024);

        return [
            'category_id.required' => 'Pilih kategori laporan.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'title.required' => 'Judul laporan wajib diisi.',
            'title.min' => 'Judul laporan minimal 5 karakter.',
            'title.max' => 'Judul laporan maksimal 255 karakter.',
            'description.required' => 'Deskripsi laporan wajib diisi.',
            'description.min' => 'Deskripsi laporan minimal 10 karakter.',
            'location_text.max' => 'Lokasi kejadian maksimal 255 karakter.',
            'attachments.max' => "Maksimal {$maxFiles} lampiran berkas yang dapat diunggah.",
            'attachments.*.mimes' => 'Format berkas lampiran harus berupa JPG, JPEG, PNG, PDF, atau MP4.',
            'attachments.*.max' => "Ukuran berkas lampiran tidak boleh melebihi {$maxMb} MB per berkas.",
        ];
    }

    /**
     * Additional validation for daily report limit.
     *
     * FINAL: max 5 complaints / user / calendar day (app timezone).
     * Value may be overridden via app_settings.daily_report_limit; when not
     * configured, the frozen default from config/business_rules.php applies.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $configured = AppSetting::get('daily_report_limit');
            $limit = ($configured !== null && is_numeric($configured) && (int) $configured > 0)
                ? (int) $configured
                : (int) config('business_rules.daily_report_limit');

            if ($limit <= 0) {
                return;
            }

            $todayCount = Complaint::where('reporter_id', $this->user()->id)
                ->whereDate('submitted_at', today())
                ->count();

            if ($todayCount >= $limit) {
                $validator->errors()->add(
                    'daily_limit',
                    'Batas pengiriman laporan harian telah tercapai. Anda hanya dapat mengirim maksimal ' . $limit . ' laporan per hari.'
                );
            }
        });
    }
}
