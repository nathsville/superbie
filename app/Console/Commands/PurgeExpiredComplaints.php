<?php

namespace App\Console\Commands;

use App\Services\ComplaintRetentionService;
use Illuminate\Console\Command;

/**
 * Permanently delete complaints past their retention period.
 *
 * Business rule (FINAL): retention = 5 years from complaints.submitted_at,
 * then permanent deletion (complaint lifecycle data + complaint-related
 * audit logs + physical attachment files).
 *
 * Safety:
 *   --dry-run : report only, no deletion
 *   --force   : required to run outside non-production environments
 */
class PurgeExpiredComplaints extends Command
{
    protected $signature = 'complaints:purge-expired
                            {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}
                            {--force : Wajib untuk menjalankan di environment production}';

    protected $description = 'Hapus permanen laporan yang telah melewati masa retensi (5 tahun sejak submitted_at).';

    public function handle(ComplaintRetentionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $isProduction = app()->environment('production');

        if ($isProduction && ! $this->option('force') && ! $dryRun) {
            $this->error('Menjalankan purge di production memerlukan flag --force.');
            return self::FAILURE;
        }

        $total = $service->expiredCount();

        if ($dryRun) {
            $this->info("DRY-RUN: {$total} laporan memenuhi syarat permanent deletion (tidak ada yang dihapus).");
            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Tidak ada laporan yang melewati masa retensi.');
            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;
        $missingFiles = 0;
        $failedFiles = 0;

        // Batch processing — never load the whole set into memory.
        $service->expiredQuery()
            ->orderBy('id')
            ->chunkById(ComplaintRetentionService::BATCH_SIZE, function ($complaints) use (&$deleted, &$failed, &$missingFiles, &$failedFiles, $service) {
                foreach ($complaints as $complaint) {
                    try {
                        $result = $service->purge($complaint);
                        $deleted++;
                        $missingFiles += $result['missing_files'];
                        $failedFiles += $result['failed_files'];
                    } catch (\Throwable $e) {
                        // One failure must not abort the whole batch.
                        $failed++;
                        $this->warn("Gagal memproses laporan #{$complaint->id}: {$e->getMessage()}");
                        report($e);
                    }
                }
            });

        $this->info("Selesai. Dihapus: {$deleted}, gagal: {$failed}, berkas tidak ditemukan: {$missingFiles}, berkas gagal dibersihkan: {$failedFiles}.");

        if ($failedFiles > 0) {
            $this->warn("Peringatan: {$failedFiles} berkas lampiran gagal dibersihkan dari storage. Hapus/verifikasi manual diperlukan (data database sudah committed).");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
