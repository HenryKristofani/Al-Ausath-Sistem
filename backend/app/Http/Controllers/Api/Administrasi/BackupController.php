<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Ambil daftar semua file backup yang tersimpan.
     *
     * GET /api/admin/backup
     */
    public function index(): JsonResponse
    {
        $backupDir = storage_path('app/backups');
        $files = glob($backupDir . '/*');
        $backups = [];

        foreach ($files as $fullPath) {
            $basename = basename($fullPath);

            // Skip file tersembunyi atau non-backup
            if (str_starts_with($basename, '.')) {
                continue;
            }

            $size = file_exists($fullPath) ? filesize($fullPath) : 0;

            // Parsing timestamp dari nama file (backup_YYYY-MM-DD_HH-ii-ss.sql.gz)
            $createdAt = null;
            if (preg_match('/backup_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})/', $basename, $matches)) {
                $dateStr   = $matches[1];
                $timeStr   = str_replace('-', ':', $matches[2]);
                $createdAt = $dateStr . ' ' . $timeStr;
            } else {
                // Fallback ke waktu modifikasi file
                $createdAt = date('Y-m-d H:i:s', filemtime($fullPath));
            }

            $backups[] = [
                'filename'   => $basename,
                'size'       => $size,
                'size_label' => $this->formatBytes($size),
                'created_at' => $createdAt,
                'compressed' => str_ends_with($basename, '.gz'),
            ];
        }

        // Urutkan dari terbaru ke terlama
        usort($backups, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return response()->json([
            'success' => true,
            'data'    => $backups,
            'total'   => count($backups),
        ]);
    }

    /**
     * Buat backup baru secara manual.
     *
     * POST /api/admin/backup/create
     */
    public function create(Request $request): JsonResponse
    {
        $compress = $request->boolean('compress', true);

        // Naikkan batas waktu eksekusi karena pg_dump bisa memakan waktu lama
        set_time_limit(300); // 5 menit

        $exitCode = Artisan::call('db:backup', [
            '--no-compress' => ! $compress,
        ]);

        if ($exitCode !== 0) {
            $output = Artisan::output();

            return response()->json([
                'success' => false,
                'message' => 'Backup gagal. Periksa konfigurasi pg_dump.',
                'detail'  => $output,
            ], 500);
        }

        $output = Artisan::output();

        // Cari file backup terbaru yang baru saja dibuat
        $backupDir = storage_path('app/backups');
        $files = glob($backupDir . '/*');
        $files = array_filter($files, fn ($f) => ! str_starts_with(basename($f), '.'));
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        $latestPath = count($files) > 0 ? $files[0] : null;
        $latestFile = $latestPath ? basename($latestPath) : null;

        return response()->json([
            'success'  => true,
            'message'  => 'Backup berhasil dibuat.',
            'data'     => [
                'filename'   => $latestFile,
                'size'       => $latestPath && file_exists($latestPath) ? filesize($latestPath) : 0,
                'size_label' => $latestPath && file_exists($latestPath)
                    ? $this->formatBytes(filesize($latestPath))
                    : '0 B',
                'created_at' => now()->format('Y-m-d H:i:s'),
                'compressed' => $compress,
            ],
            'log'      => trim($output),
        ]);
    }

    /**
     * Download file backup.
     *
     * GET /api/admin/backup/{filename}/download
     */
    public function download(string $filename): StreamedResponse|JsonResponse
    {
        // Sanitasi nama file — hanya izinkan karakter aman
        if (! preg_match('/^[\w\-\.]+$/', $filename)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama file tidak valid.',
            ], 422);
        }

        $filePath = storage_path('app/backups/' . $filename);

        if (! file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File backup tidak ditemukan.',
            ], 404);
        }

        return response()->streamDownload(function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            while (! feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => filesize($filePath),
        ]);
    }

    /**
     * Hapus file backup.
     *
     * DELETE /api/admin/backup/{filename}
     */
    public function destroy(string $filename): JsonResponse
    {
        // Sanitasi nama file
        if (! preg_match('/^[\w\-\.]+$/', $filename)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama file tidak valid.',
            ], 422);
        }

        $filePath = 'backups/' . $filename;

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File backup tidak ditemukan.',
            ], 404);
        }

        Storage::disk('local')->delete($filePath);

        return response()->json([
            'success' => true,
            'message' => 'File backup berhasil dihapus.',
        ]);
    }

    /**
     * Format bytes ke ukuran yang mudah dibaca.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
