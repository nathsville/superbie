<?php

namespace App\Http\Requests\Citizen;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PHASE 2 — Citizen correction / resubmission of a returned complaint.
 *
 * Rules mirror the frozen create rules (category active, title/description
 * bounds, structured location pair, attachment rules) with two PHASE 2
 * differences:
 *   - The attachment cap counts EXISTING attachments + newly uploaded ones,
 *     minus the ones the reporter removes.
 *   - There is NO daily-limit check: a resubmission updates the SAME complaint
 *     (it is not a create), so it must never consume or bypass the daily limit.
 *
 * The request deliberately does NOT accept dinas_unit_id, assigned_to, status or
 * review_state — those are not citizen-owned and are never read from input.
 */
class UpdateComplaintRequest extends FormRequest
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
                    if (! $category || ! $category->is_active) {
                        $fail('Kategori yang dipilih tidak aktif atau tidak ditemukan.');
                    }
                },
            ],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'kecamatan_id' => ['required', 'integer', 'exists:kecamatan,id'],
            'kelurahan_id' => [
                'required',
                'integer',
                Rule::exists('kelurahan', 'id')->where(function ($query) {
                    $query->where('kecamatan_id', $this->input('kecamatan_id'));
                }),
            ],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'mimes:' . implode(',', config('business_rules.attachment.mimes')),
                'max:' . config('business_rules.attachment.max_kilobytes'),
            ],
            // Existing attachments the reporter wants to delete (owner-scoped).
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
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
            'attachments.*.mimes' => 'Format berkas lampiran harus berupa JPG, JPEG, PNG, PDF, atau MP4.',
            'attachments.*.max' => "Ukuran berkas lampiran tidak boleh melebihi {$maxMb} MB per berkas.",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Coordinate PAIR rule (both-or-neither), identical to the create form.
        $validator->after(function ($validator) {
            $hasLat = $this->input('latitude') !== null && $this->input('latitude') !== '';
            $hasLng = $this->input('longitude') !== null && $this->input('longitude') !== '';

            if ($hasLat !== $hasLng) {
                $message = 'Latitude dan longitude harus diisi bersamaan.';
                $validator->errors()->add('latitude', $message);
                $validator->errors()->add('longitude', $message);
            }
        });

        // Attachment COUNT rule: the FINAL cap is on the TOTAL number of files
        // after the correction (kept + newly added), never more than the frozen
        // maximum. Removal is owner-scoped to THIS complaint.
        $validator->after(function ($validator) {
            $maxFiles = (int) config('business_rules.attachment.max_files');

            /** @var Complaint|null $complaint */
            $complaint = $this->route('complaint');
            if (! $complaint instanceof Complaint) {
                return;
            }

            $existing = ComplaintAttachment::where('complaint_id', $complaint->id)->count();

            $removeIds = collect($this->input('remove_attachments', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();

            // Only count removals that actually belong to this complaint.
            $removed = $removeIds === []
                ? 0
                : ComplaintAttachment::where('complaint_id', $complaint->id)
                    ->whereIn('id', $removeIds)
                    ->count();

            $kept = max(0, $existing - $removed);
            $added = count($this->file('attachments') ?? []);
            $total = $kept + $added;

            if ($total > $maxFiles) {
                $validator->errors()->add(
                    'attachments',
                    "Total lampiran tidak boleh melebihi {$maxFiles} berkas."
                );
            }
        });
    }
}
