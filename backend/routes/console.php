<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('raport:backfill-nilai-mapel {--dry-run : Simulasi tanpa update database}', function () {
    $dryRun = (bool) $this->option('dry-run');

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
    ];

    DB::table('data_nilai_siswa')
        ->select([
            'id_nilai',
            'nilai_harian',
            'nilai_uts',
            'nilai_uas',
            'nilai_akhir_mapel',
            'nilai_rapor_tampil',
            'flag_warna_rapor',
        ])
        ->orderBy('id_nilai')
        ->chunkById(500, function ($rows) use (&$stats, $dryRun, $roundHalfUp, $roundRaporInteger, $normalizeNilaiRapor) {
            foreach ($rows as $row) {
                $stats['processed']++;

                $rawValue = null;

                if ($row->nilai_akhir_mapel !== null) {
                    $rawValue = (float) $row->nilai_akhir_mapel;
                } elseif ($row->nilai_harian !== null && $row->nilai_uts !== null && $row->nilai_uas !== null) {
                    $rawValue =
                        (((float) $row->nilai_harian) * 0.20)
                        + (((float) $row->nilai_uts) * 0.30)
                        + (((float) $row->nilai_uas) * 0.50);
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
})->purpose('Sinkronisasi nilai_akhir_mapel, nilai_rapor_tampil, dan flag_warna_rapor untuk data lama');
