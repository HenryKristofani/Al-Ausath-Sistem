<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataKelasMapel;
use App\Models\DataNilaiSiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Teacher Analytics Dashboard
 * 
 * Endpoints untuk dashboard pengajar dengan statistik nilai, 
 * rekap per mata pelajaran, dan distribusi nilai santri.
 */
class AnalyticsController extends Controller
{
    /**
     * Statistik nilai kelas yang diampu.
     * 
     * Menampilkan nilai rata-rata keseluruhan per kelas yang diajar 
     * oleh pengajar yang sedang login.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function classStatistics(Request $request): JsonResponse
    {
        $petugasId = $request->user()->id_petugas;

        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        // Ambil daftar kelas yang di-wali-kan oleh petugas
        $kelasWali = \App\Models\DataKelas::where('id_wali_kelas', $petugasId)
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->pluck('kode_kelas')
            ->toArray();

        // Ambil daftar kelas mapel yang diajar petugas ATAU bagian dari kelas perwaliannya
        $kelasMapel = DataKelasMapel::query()
            ->where(function ($query) use ($petugasId, $kelasWali) {
                $query->where('id_petugas', $petugasId);
                if (!empty($kelasWali)) {
                    $query->orWhereIn('kode_kelas', $kelasWali);
                }
            })
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), 
                fn($q) => $q->where('semester', $validated['semester']))
            ->with(['kelas', 'mataPelajaran'])
            ->get();

        $result = $kelasMapel->map(function ($km) use ($validated) {
            $stats = DataNilaiSiswa::where('kode_kelas', $km->kode_kelas)
                ->where('kode_mapel', $km->kode_mapel)
                ->when(!empty($validated['tahun_ajaran']), 
                    fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
                ->when(!empty($validated['semester']), 
                    fn($q) => $q->where('semester', $validated['semester']))
                ->select(
                    DB::raw('COALESCE(AVG(nilai_akhir_mapel), 0) as rata_rata'),
                    DB::raw('MAX(nilai_akhir_mapel) as tertinggi'),
                    DB::raw('MIN(nilai_akhir_mapel) as terendah'),
                    DB::raw('COUNT(DISTINCT nomor_induk) as jumlah_santri')
                )
                ->first();

            return [
                'kode_kelas' => $km->kode_kelas,
                'nama_kelas' => $km->kelas->nama_kelas ?? '-',
                'kode_mapel' => $km->kode_mapel,
                'nama_mapel' => $km->mataPelajaran->nama_mapel ?? '-',
                'rata_rata' => round($stats->rata_rata ?? 0, 2),
                'tertinggi' => $stats->tertinggi ?? 0,
                'terendah' => $stats->terendah ?? 0,
                'jumlah_santri' => $stats->jumlah_santri ?? 0,
            ];
        })->filter(function ($item) {
            return $item['jumlah_santri'] > 0;
        });

        return response()->json([
            'data' => $result->values(),
            'filters' => $validated,
        ]);
    }

    /**
     * Rekap nilai per mata pelajaran.
     * 
     * Breakdown nilai Harian, UTS, dan UAS per mata pelajaran 
     * yang diampu oleh pengajar.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function subjectRecap(Request $request): JsonResponse
    {
        $petugasId = $request->user()->id_petugas;

        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'kode_kelas' => ['nullable', 'string', 'max:10'],
        ]);

        // Kelas yang di-wali-kan
        $kelasWali = \App\Models\DataKelas::where('id_wali_kelas', $petugasId)
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->pluck('kode_kelas')
            ->toArray();

        // Query nilai per mapel yang diajar atau di-wali-kan
        $query = DataNilaiSiswa::query()
            ->joinSub(
                DataKelasMapel::query()
                    ->where(function ($q) use ($petugasId, $kelasWali) {
                        $q->where('id_petugas', $petugasId);
                        if (!empty($kelasWali)) {
                            $q->orWhereIn('kode_kelas', $kelasWali);
                        }
                    })
                    ->select('kode_kelas', 'kode_mapel'),
                'kelas_mapel',
                function ($join) {
                    $join->on('data_nilai_siswa.kode_kelas', '=', 'kelas_mapel.kode_kelas')
                        ->on('data_nilai_siswa.kode_mapel', '=', 'kelas_mapel.kode_mapel');
                }
            )
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('data_nilai_siswa.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), 
                fn($q) => $q->where('data_nilai_siswa.semester', $validated['semester']))
            ->when(!empty($validated['kode_kelas']), 
                fn($q) => $q->where('data_nilai_siswa.kode_kelas', $validated['kode_kelas']));

        $recap = $query
            ->groupBy('data_nilai_siswa.kode_mapel')
            ->select(
                'data_nilai_siswa.kode_mapel',
                DB::raw('COALESCE(AVG(data_nilai_siswa.nilai_harian), 0) as rata_harian'),
                DB::raw('COALESCE(AVG(data_nilai_siswa.nilai_uts), 0) as rata_uts'),
                DB::raw('COALESCE(AVG(data_nilai_siswa.nilai_uas), 0) as rata_uas'),
                DB::raw('COALESCE(AVG(data_nilai_siswa.nilai_akhir_mapel), 0) as rata_akhir'),
                DB::raw('COUNT(DISTINCT data_nilai_siswa.nomor_induk) as jumlah_santri')
            )
            ->get();

        // Enrichment nama mapel
        $result = $recap->map(function ($item) {
            $mapel = \App\Models\DataMataPelajaran::where('kode_mapel', $item->kode_mapel)->first();
            return [
                'kode_mapel' => $item->kode_mapel,
                'nama_mapel' => $mapel->nama_mapel ?? '-',
                'rata_harian' => round($item->rata_harian, 2),
                'rata_uts' => round($item->rata_uts, 2),
                'rata_uas' => round($item->rata_uas, 2),
                'rata_akhir' => round($item->rata_akhir, 2),
                'jumlah_santri' => $item->jumlah_santri,
            ];
        });

        return response()->json([
            'data' => $result->values(),
            'filters' => $validated,
        ]);
    }

    /**
     * Distribusi nilai santri.
     * 
     * Jumlah santri yang masuk dalam rentang nilai tertentu 
     * (90-100, 80-89, 70-79, 60-69, <60) di kelas yang diampu.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function scoreDistribution(Request $request): JsonResponse
    {
        $petugasId = $request->user()->id_petugas;

        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'kode_mapel' => ['nullable', 'string', 'max:20'],
        ]);

        // Kelas yang di-wali-kan
        $kelasWali = \App\Models\DataKelas::where('id_wali_kelas', $petugasId)
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->pluck('kode_kelas')
            ->toArray();

        // Query nilai santri dari mapel yang diajar atau di-wali-kan
        $query = DataNilaiSiswa::query()
            ->joinSub(
                DataKelasMapel::query()
                    ->where(function ($q) use ($petugasId, $kelasWali) {
                        $q->where('id_petugas', $petugasId);
                        if (!empty($kelasWali)) {
                            $q->orWhereIn('kode_kelas', $kelasWali);
                        }
                    })
                    ->select('kode_kelas', 'kode_mapel'),
                'kelas_mapel',
                function ($join) {
                    $join->on('data_nilai_siswa.kode_kelas', '=', 'kelas_mapel.kode_kelas')
                        ->on('data_nilai_siswa.kode_mapel', '=', 'kelas_mapel.kode_mapel');
                }
            )
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('data_nilai_siswa.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), 
                fn($q) => $q->where('data_nilai_siswa.semester', $validated['semester']))
            ->when(!empty($validated['kode_kelas']), 
                fn($q) => $q->where('data_nilai_siswa.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['kode_mapel']), 
                fn($q) => $q->where('data_nilai_siswa.kode_mapel', $validated['kode_mapel']))
            ->select('data_nilai_siswa.nilai_akhir_mapel');

        $values = $query->pluck('nilai_akhir_mapel')->toArray();

        // Hitung distribusi
        $ranges = [
            ['min' => 90, 'max' => 100, 'label' => '90-100 (A)'],
            ['min' => 80, 'max' => 89, 'label' => '80-89 (B)'],
            ['min' => 70, 'max' => 79, 'label' => '70-79 (C)'],
            ['min' => 60, 'max' => 69, 'label' => '60-69 (D)'],
            ['min' => 0, 'max' => 59, 'label' => '0-59 (E)'],
        ];

        $distribution = array_map(function ($range) use ($values) {
            $count = count(array_filter($values, fn($v) => $v >= $range['min'] && $v <= $range['max']));
            return [
                'range' => $range['label'],
                'min' => $range['min'],
                'max' => $range['max'],
                'count' => $count,
                'percentage' => !empty($values) ? round(($count / count($values)) * 100, 2) : 0,
            ];
        }, $ranges);

        return response()->json([
            'data' => $distribution,
            'total_santri' => count($values),
            'filters' => $validated,
        ]);
    }
}
