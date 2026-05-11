<?php

use App\Models\DataSantri;
use App\Models\PpdbPendaftar;
use App\Support\PpdbRegistrationNumberService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('raport:backfill-nilai-mapel {--dry-run : Simulasi tanpa update database}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $bobotCache = [];

    $roundHalfUp = static function (float $value, int $precision): float {
        $factor = 10 ** $precision;

        return floor(($value * $factor) + 0.5) / $factor;
    };

    $roundRaporInteger = static function (float $nilai): int {
        $desimal = $nilai - floor($nilai);

        return $desimal >= 0.5 ? (int) ceil($nilai) : (int) floor($nilai);
    };

    $normalizeNilaiRapor = static function (float $nilaiAkhirMentah, int $nilaiRaporBulat): array {
        if ($nilaiRaporBulat > 98) {
            $nilaiRaporBulat = 98;
        }

        if ($nilaiAkhirMentah < 50 || $nilaiRaporBulat < 50) {
            return [50, 'MERAH'];
        }

        return [$nilaiRaporBulat, 'HITAM'];
    };

    $stats = [
        'processed' => 0,
        'updated' => 0,
        'skipped_no_source' => 0,
        'skipped_no_bobot' => 0,
    ];

    $resolveBobot = static function (string $tahunAjaran, int $semester) use (&$bobotCache): ?array {
        $key = $tahunAjaran . '|' . $semester;

        if (array_key_exists($key, $bobotCache)) {
            return $bobotCache[$key];
        }

        $bobot = DB::table('bobot_nilai')
            ->where('jenjang', 'GLOBAL')
            ->whereNull('kode_unit')
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderByDesc('id_bobot')
            ->first(['bobot_harian', 'bobot_uts', 'bobot_uas']);

        if (! $bobot) {
            $bobotCache[$key] = null;

            return null;
        }

        $bobotCache[$key] = [
            'harian' => ((float) $bobot->bobot_harian) / 100,
            'uts' => ((float) $bobot->bobot_uts) / 100,
            'uas' => ((float) $bobot->bobot_uas) / 100,
        ];

        return $bobotCache[$key];
    };

    DB::table('data_nilai_siswa')
        ->select([
            'id_nilai',
            'tahun_ajaran',
            'semester',
            'nilai_harian',
            'nilai_uts',
            'nilai_uas',
            'nilai_akhir_mapel',
            'nilai_rapor_tampil',
            'flag_warna_rapor',
        ])
        ->orderBy('id_nilai')
        ->chunkById(500, function ($rows) use (&$stats, $dryRun, $roundHalfUp, $roundRaporInteger, $normalizeNilaiRapor, $resolveBobot) {
            foreach ($rows as $row) {
                $stats['processed']++;

                $rawValue = null;

                if ($row->nilai_akhir_mapel !== null) {
                    $rawValue = (float) $row->nilai_akhir_mapel;
                } elseif ($row->nilai_harian !== null && $row->nilai_uts !== null && $row->nilai_uas !== null) {
                    $bobot = $resolveBobot((string) $row->tahun_ajaran, (int) $row->semester);

                    if (! $bobot) {
                        $stats['skipped_no_bobot']++;

                        continue;
                    }

                    $rawValue =
                        (((float) $row->nilai_harian) * $bobot['harian'])
                        + (((float) $row->nilai_uts) * $bobot['uts'])
                        + (((float) $row->nilai_uas) * $bobot['uas']);
                }

                if ($rawValue === null) {
                    $stats['skipped_no_source']++;
                    continue;
                }

                $nilaiAkhirMapel = $roundHalfUp($rawValue, 2);
                $nilaiRaporBulat = $roundRaporInteger($rawValue);
                [$nilaiRaporTampil, $flagWarna] = $normalizeNilaiRapor($rawValue, $nilaiRaporBulat);

                $currentNilaiAkhirMapel = $row->nilai_akhir_mapel !== null
                    ? $roundHalfUp((float) $row->nilai_akhir_mapel, 2)
                    : null;

                $currentNilaiRaporTampil = $row->nilai_rapor_tampil !== null
                    ? (int) $row->nilai_rapor_tampil
                    : null;

                $currentFlagWarna = $row->flag_warna_rapor !== null
                    ? strtoupper((string) $row->flag_warna_rapor)
                    : null;

                $isChanged =
                    $currentNilaiAkhirMapel !== $nilaiAkhirMapel
                    || $currentNilaiRaporTampil !== $nilaiRaporTampil
                    || $currentFlagWarna !== $flagWarna;

                if (! $isChanged) {
                    continue;
                }

                $stats['updated']++;

                if (! $dryRun) {
                    DB::table('data_nilai_siswa')
                        ->where('id_nilai', $row->id_nilai)
                        ->update([
                            'nilai_akhir_mapel' => $nilaiAkhirMapel,
                            'nilai_rapor_tampil' => $nilaiRaporTampil,
                            'flag_warna_rapor' => $flagWarna,
                        ]);
                }
            }
        }, 'id_nilai');

    $mode = $dryRun ? 'DRY-RUN' : 'APPLY';

    $this->info('Mode: ' . $mode);
    $this->line('Processed: ' . $stats['processed']);
    $this->line('Updated: ' . $stats['updated']);
    $this->line('Skipped (no source): ' . $stats['skipped_no_source']);
    $this->line('Skipped (no bobot): ' . $stats['skipped_no_bobot']);
})->purpose('Sinkronisasi nilai_akhir_mapel, nilai_rapor_tampil, dan flag_warna_rapor untuk data lama');

// ─── Fix PPDB Payments: Link id_santri to Integrated Students ────────────────
Artisan::command('spp:fix-ppdb-payments {--dry-run : Simulasi tanpa update database}', function () {
    $dryRun  = (bool) $this->option('dry-run');
    $mode    = $dryRun ? 'DRY-RUN' : 'APPLY';
    $this->info("Mode: {$mode}");
    $this->line('Mencari PPDB payment yang belum terhubung ke id_santri...');

    // Find all PPDB payments without id_santri
    $payments = DB::table('pembayaran_spp')
        ->whereNotNull('id_pendaftaran')
        ->whereNull('id_santri')
        ->select(['id_pembayaran', 'id_pendaftaran'])
        ->get();

    $this->line("Ditemukan {$payments->count()} PPDB payment tanpa id_santri.");

    $nomorService = app(PpdbRegistrationNumberService::class);
    $updated = 0;
    $notFound = 0;

    foreach ($payments as $payment) {
        $pendaftar = PpdbPendaftar::find($payment->id_pendaftaran);

        if (!$pendaftar) {
            $this->line("  SKIP id_pembayaran={$payment->id_pembayaran}: pendaftar tidak ditemukan.");
            $notFound++;
            continue;
        }

        // Generate the base nomor_induk without the 'used' check tail
        $tanggal = $pendaftar->tanggal_daftar ? \Illuminate\Support\Carbon::parse($pendaftar->tanggal_daftar) : now();
        
        // We need to access private methods of nomorService or just replicate the base logic
        // Let's try to find a santri that matches the pendaftar more broadly
        
        // 1. Try exact match with the pendaftar's name and a nomor_induk that "looks" right
        $santri = DataSantri::where('nama_lengkap_santri', 'ILIKE', $pendaftar->nama_calon)
            ->where(function($q) {
                $q->where('nomor_induk', 'LIKE', 'NIS%')
                  ->orWhere('nomor_induk', 'LIKE', '%/%/%'); // Handle SMP/2026/001 format too
            })
            ->first();

        if (!$santri) {
            // 2. Fallback: Generate the nomor_induk and try to match the "taken" one
            $expectedNomorInduk = $nomorService->generateNomorIndukFromPendaftaran($pendaftar);
            $santri = DataSantri::where('nomor_induk', $expectedNomorInduk)->first();
            
            if (!$santri) {
                // Try removing the '01', '02' etc suffix if it was added
                $baseWithoutSuffix = preg_replace('/\d{2}$/', '', $expectedNomorInduk);
                $santri = DataSantri::where('nomor_induk', $baseWithoutSuffix)->first();
            }
        }

        if (!$santri) {
            $this->line("  SKIP id_pembayaran={$payment->id_pembayaran} (id_pendaftaran={$payment->id_pendaftaran}): santri tidak ditemukan.");
            $notFound++;
            continue;
        }

        $this->line("  id_pembayaran={$payment->id_pembayaran} → id_santri={$santri->id_santri} (nomor_induk={$santri->nomor_induk}, nama={$santri->nama_lengkap_santri})");

        if (!$dryRun) {
            DB::table('pembayaran_spp')
                ->where('id_pembayaran', $payment->id_pembayaran)
                ->update(['id_santri' => $santri->id_santri]);
        }

        $updated++;
    }

    $this->info("{$updated} record " . ($dryRun ? 'akan diperbarui (simulasi).' : 'berhasil diperbarui.'));
    if ($notFound > 0) {
        $this->warn("{$notFound} record tidak dapat ditemukan pasangan santrinya.");
    }
})->purpose('Menghubungkan id_santri pada PPDB payment yang sudah terintegrasi ke santri (fix data lama)');
