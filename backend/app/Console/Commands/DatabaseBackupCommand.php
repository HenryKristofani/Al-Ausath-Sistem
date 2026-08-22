<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--filename= : Custom filename (tanpa ekstensi)}
                            {--no-compress : Simpan sebagai .sql biasa tanpa compress}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat backup database PostgreSQL ke storage/app/backups/';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('⏳ Memulai proses backup database...');

        // Pastikan folder backups ada
        if (! Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }

        // Ambil konfigurasi database dari config (membaca dari .env)
        $connection = config('database.default');
        $dbConfig   = config("database.connections.{$connection}");

        if ($dbConfig['driver'] !== 'pgsql') {
            $this->error('❌ Command ini hanya mendukung PostgreSQL (pgsql).');

            return self::FAILURE;
        }

        // Ambil konfigurasi database dari .env via config()
        // Catatan: config('...host') untuk pgsql mungkin mengandung ';options=...' 
        // yang ditambahkan Laravel khusus untuk Neon.tech. Kita strip bagian itu
        // karena pg_dump tidak mengerti format tersebut.
        $rawHost  = $dbConfig['host'] ?? '127.0.0.1';
        $host     = explode(';', $rawHost)[0]; // ambil host bersih sebelum ';'
        $port     = $dbConfig['port'] ?? 5432;
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'] ?? '';
        $sslmode  = $dbConfig['sslmode'] ?? 'prefer';

        // Deteksi apakah ada options untuk Neon (endpoint=...) dari config
        // Ini ada di dalam raw host string: 'host;options=endpoint=xxx'
        $pgOptions = '';
        if (str_contains($rawHost, ';options=')) {
            // Ekstrak nilai options, misal: 'endpoint=ep-xxx'
            $pgOptions = substr($rawHost, strpos($rawHost, ';options=') + strlen(';options='));
        }

        // Generate nama file
        $customFilename = $this->option('filename');
        $timestamp      = now()->format('Y-m-d_H-i-s');
        $baseFilename   = $customFilename ?: "backup_{$timestamp}";
        $compress       = ! $this->option('no-compress');

        // Simpan dulu sebagai .sql sementara, lalu compress dengan PHP zlib
        $tmpFilename = $baseFilename . '.sql';
        $tmpPath     = storage_path('app/backups/' . $tmpFilename);

        // Final output
        $finalFilename = $compress ? $baseFilename . '.sql.gz' : $tmpFilename;
        $finalPath     = storage_path('app/backups/' . $finalFilename);

        // Cari path pg_dump
        $pgDumpPath = $this->findPgDump();

        if (! $pgDumpPath) {
            $this->error('❌ pg_dump tidak ditemukan. Set PGDUMP_PATH di .env atau pastikan PostgreSQL terinstall.');
            $this->line('  Contoh .env: PGDUMP_PATH=C:\\Program Files\\PostgreSQL\\17\\bin\\pg_dump.exe');

            return self::FAILURE;
        }

        $this->line("🔧 Menggunakan pg_dump: {$pgDumpPath}");
        $this->line("🗄️  Database: {$database} di {$host}:{$port}");

        // Build command pg_dump menggunakan argumen terpisah
        // Catatan: pg_dump bisa koneksi ke Neon.tech langsung via hostname + PGSSLMODE
        // Tidak perlu PGOPTIONS atau endpoint parameter
        $escapedPgDump = '"' . str_replace('"', '\"', $pgDumpPath) . '"';

        $this->line('   🔗 Menghubungkan ke database...');

        $pgDumpArgs = [
            '--host=' . $host,
            '--port=' . $port,
            '--username=' . $username,
            '--dbname=' . $database,
            '--no-password',
            '--format=plain',
            '--file=' . $tmpPath,
        ];

        $pgDumpCmd = $escapedPgDump . ' ' . implode(' ', array_map(
            fn ($arg) => '"' . str_replace('"', '\"', $arg) . '"',
            $pgDumpArgs
        ));

        // Set PGPASSWORD dan PGSSLMODE via putenv agar diwarisi child process
        // Kita gunakan putenv() (bukan array env di proc_open) agar child process
        // tetap mewarisi PATH, DNS resolver, dan env lainnya dari parent process
        putenv("PGPASSWORD={$password}");
        putenv("PGSSLMODE={$sslmode}");

        // Jalankan pg_dump — pass null sebagai env agar inherit env parent (termasuk PATH, DNS, dll)

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->line('🚀 Menjalankan pg_dump...');

        $process = proc_open($pgDumpCmd, $descriptorSpec, $pipes, null, null);

        if (! is_resource($process)) {
            $this->error('❌ Gagal membuka proses pg_dump.');

            return self::FAILURE;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->error('❌ pg_dump gagal (exit code: ' . $exitCode . ')');
            if ($stderr) {
                $this->error('   Error: ' . trim($stderr));
            }

            // Hapus file partial jika ada
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }

            return self::FAILURE;
        }

        if (! file_exists($tmpPath) || filesize($tmpPath) === 0) {
            $this->error('❌ File SQL tidak terbuat atau kosong setelah pg_dump.');

            return self::FAILURE;
        }

        $this->line('   ✔ pg_dump selesai, ukuran SQL: ' . $this->formatBytes(filesize($tmpPath)));

        // Compress menggunakan PHP zlib (tidak butuh gzip di system)
        if ($compress) {
            $this->line('📦 Mengompresi dengan gzip...');

            $compressed = $this->compressFile($tmpPath, $finalPath);

            // Hapus file .sql sementara
            unlink($tmpPath);

            if (! $compressed) {
                $this->error('❌ Gagal mengompresi file backup.');

                return self::FAILURE;
            }
        }

        if (! file_exists($finalPath) || filesize($finalPath) === 0) {
            $this->error('❌ File backup final tidak terbuat atau kosong.');

            return self::FAILURE;
        }

        $fileSize = $this->formatBytes(filesize($finalPath));

        $this->info('✅ Backup berhasil!');
        $this->line("   📁 File : {$finalFilename}");
        $this->line("   📦 Size : {$fileSize}");
        $this->line("   📂 Path : {$finalPath}");

        return self::SUCCESS;
    }

    /**
     * Compress file menggunakan PHP zlib (tidak perlu gzip di system).
     */
    private function compressFile(string $sourcePath, string $destPath): bool
    {
        $source = fopen($sourcePath, 'rb');
        if (! $source) {
            return false;
        }

        // Buka file gz untuk menulis
        $dest = gzopen($destPath, 'wb9'); // level kompresi 9 (maksimal)
        if (! $dest) {
            fclose($source);

            return false;
        }

        while (! feof($source)) {
            $buffer = fread($source, 65536); // 64KB chunk
            gzwrite($dest, $buffer);
        }

        fclose($source);
        gzclose($dest);

        return file_exists($destPath) && filesize($destPath) > 0;
    }

    /**
     * Cari path pg_dump secara otomatis.
     */
    private function findPgDump(): ?string
    {
        // 1. Cek dari .env
        $envPath = env('PGDUMP_PATH');
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        // 2. Coba dari PATH system
        $which = $this->isWindows() ? 'where pg_dump 2>NUL' : 'which pg_dump 2>/dev/null';
        $found = shell_exec($which);
        if ($found) {
            $path = trim(explode("\n", $found)[0]);
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        // 3. Path umum Windows (berbagai versi PostgreSQL)
        if ($this->isWindows()) {
            foreach (range(17, 12) as $version) {
                $path = "C:\\Program Files\\PostgreSQL\\{$version}\\bin\\pg_dump.exe";
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        // 4. Path umum Linux/Mac
        foreach (['/usr/bin/pg_dump', '/usr/local/bin/pg_dump', '/opt/homebrew/bin/pg_dump'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Cek apakah running di Windows.
     */
    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
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
