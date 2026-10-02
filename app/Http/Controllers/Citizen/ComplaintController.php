<?php

namespace App\Http\Controllers\Citizen;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StoreComplaintRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ComplaintController extends Controller
{
    /**
     * Show form to create a new complaint.
     */
    public function create(): View
    {
        $categories = ComplaintCategory::active()->get();

        return view('citizen.complaint.create', compact('categories'));
    }

    /**
     * Store newly created complaint in storage.
     */
    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $complaint = DB::transaction(function () use ($user, $validated, $request) {
            // Generate unique reference code: LPW-YYYYMMDD-XXXX
            $datePrefix = 'LPW-' . now()->format('Ymd') . '-';
            $uniqueCode = null;
            do {
                $candidate = $datePrefix . strtoupper(Str::random(4));
            } while (Complaint::where('reference_code', $candidate)->exists());
            $uniqueCode = $candidate;

            // Generate tracking secret hash for public lookup
            $trackingSecret = Str::random(32);
            $trackingHash = hash('sha256', $trackingSecret);

            $complaint = Complaint::create([
                'reference_code' => $uniqueCode,
                'tracking_secret_hash' => $trackingHash,
                'category_id' => $validated['category_id'],
                'reporter_id' => $user->id,
                'reporter_email' => $user->email,
                'reporter_phone' => $user->phone ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'location_text' => $validated['location_text'] ?? null,
                'status' => ComplaintStatus::Submitted,
                'submitted_at' => now(),
            ]);

            // Save attachments in private storage
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (!$file->isValid()) {
                        continue;
                    }

                    $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('complaints/' . $complaint->id, $filename, 'private');
                    $checksum = hash_file('sha256', $file->getRealPath());

                    ComplaintAttachment::create([
                        'complaint_id' => $complaint->id,
                        'disk' => 'private',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'checksum_sha256' => $checksum,
                    ]);
                }
            }

            // Create initial status history entry
            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status' => null,
                'to_status' => ComplaintStatus::Submitted->value,
                'changed_by' => $user->id,
                'note' => 'Laporan berhasil dibuat dan diajukan ke sistem.',
            ]);

            // Write audit log
            AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'complaint_created',
                'subject_type' => 'complaint',
                'subject_id' => $complaint->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'reference_code' => $complaint->reference_code,
                    'category_id' => $complaint->category_id,
                ],
            ]);

            return $complaint;
        });

        return redirect()->route('citizen.complaint.show', $complaint)->with(
            'success',
            'Laporan berhasil dikirim dengan Nomor Registrasi: ' . $complaint->reference_code
        );
    }

    /**
     * Display the specified complaint.
     * Citizen can ONLY view their own complaints.
     * Internal staff notes are NEVER exposed.
     */
    public function show(Complaint $complaint): View
    {
        // STRICT Server-side authorization check
        if ($complaint->reporter_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat laporan ini.');
        }

        // Eager load only citizen-safe relations
        $complaint->load([
            'category',
            'attachments',
            'statusHistories.changedBy',
            'publicResponses.author',
        ]);

        return view('citizen.complaint.show', compact('complaint'));
    }

    /**
     * Securely download or view an attachment belonging to own complaint.
     */
    public function downloadAttachment(Complaint $complaint, ComplaintAttachment $attachment): Response
    {
        // STRICT Server-side authorization check
        if ($complaint->reporter_id !== auth()->id() || $attachment->complaint_id !== $complaint->id) {
            abort(403, 'Akses berkas ditolak.');
        }

        $disk = Storage::disk($attachment->disk);
        if (!$disk->exists($attachment->path)) {
            abort(404, 'Berkas lampiran tidak ditemukan di server.');
        }

        return $disk->response($attachment->path, $attachment->original_name);
    }
}
