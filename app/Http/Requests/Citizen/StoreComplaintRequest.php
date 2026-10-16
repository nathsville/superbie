<?php

namespace App\Http\Requests\Citizen;

use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            // OPTIONAL geographic coordinates (Prompt 4/5). Range is enforced
            // server-side; the pair rule (both-or-neither) is enforced in
            // withValidator() below. A complaint is ALWAYS valid without
            // coordinates — GPS/manual pin are optional enhancements.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            // FINAL location rule: Kecamatan AND Kelurahan are REQUIRED for every
            // new complaint (all categories, no exceptions). `location_text`
            // remains an OPTIONAL free-form detail on top of the structured pair.
            'kecamatan_id' => ['required', 'integer', 'exists:kecamatan,id'],
            'kelurahan_id' => [
                'required',
                'integer',
                // Cross-check (security boundary): the Kelurahan MUST belong to
                // the submitted Kecamatan. `exists:kelurahan,id` alone is NOT
                // enough — it would allow Kecamatan A + Kelurahan of Kecamatan B.
                Rule::exists('kelurahan', 'id')->where(function ($query) {
                    $query->where('kecamatan_id', $this->input('kecamatan_id'));
                }),
            ],
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
            'latitude.numeric' => 'Latitude tidak valid.',
            'latitude.between' => 'Latitude harus berada di antara -90 dan 90.',
            'longitude.numeric' => 'Longitude tidak valid.',
            'longitude.between' => 'Longitude harus berada di antara -180 dan 180.',
            'kecamatan_id.required' => 'Kecamatan wajib dipilih.',
            'kecamatan_id.integer' => 'Kecamatan tidak valid.',
            'kecamatan_id.exists' => 'Kecamatan yang dipilih tidak ditemukan.',
            'kelurahan_id.required' => 'Kelurahan wajib dipilih.',
            'kelurahan_id.integer' => 'Kelurahan tidak valid.',
            'kelurahan_id.exists' => 'Kelurahan yang dipilih tidak sesuai dengan Kecamatan.',
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
        // Coordinate PAIR rule (security boundary, Prompt 4/5 §E): latitude and
        // longitude are optional, but they are all-or-nothing. A partial pair
        // (only one of the two present) is REJECTED — the client is never
        // trusted. Empty strings are normalised to null by the framework's
        // ConvertEmptyStringsToNull middleware, so an unchecked hidden input
        // arrives as null (valid "no coordinates").
        $validator->after(function ($validator) {
            $hasLat = $this->input('latitude') !== null && $this->input('latitude') !== '';
            $hasLng = $this->input('longitude') !== null && $this->input('longitude') !== '';

            if ($hasLat !== $hasLng) {
                $message = 'Latitude dan longitude harus diisi bersamaan.';
                $validator->errors()->add('latitude', $message);
                $validator->errors()->add('longitude', $message);
            }
        });

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
