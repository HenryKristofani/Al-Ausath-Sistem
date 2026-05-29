<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataNilaiSiswa;
use App\Models\KkmMapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Student Analytics Dashboard
 * 
 * Endpoints untuk dashboard santri dengan nilai per mata pelajaran,
 * grafik trend perkembangan, dan progress tracking akademik.
 */
class SantriAnalyticsController extends Controller
{
    /**
     * Nilai per mata pelajaran semester saat ini.
     * 
     * Menampilkan semua nilai santri yang login, dikelompokkan per mata pelajaran,
     * beserta breakdown nilai Harian, UTS, UAS, dan nilai akhir.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function subjectScores(Request $request): JsonResponse
    {
        $nomorInduk = $request->user()->nomor_induk;

        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        // Default semester sekarang jika tidak diberikan
        if (empty($validated['semester'])) {
            $latestSemester = DataNilaiSiswa::where('nomor_induk', $nomorInduk)
                ->orderByDesc('semester')
                ->value('semester');
            $validated['semester'] = $latestSemester ?? 1;
        }

        $query = DataNilaiSiswa::where('nomor_induk', $nomorInduk)
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), 
                fn($q) => $q->where('semester', $validated['semester']))
            ->select(
                'kode_mapel',
                'nilai_harian',
                'nilai_uts',
                'nilai_uas',
                'nilai_akhir_mapel',
                'status_ketuntasan',
                'nilai_rapor_tampil'
            );

        $scores = $query->get();

        // Enrichment nama mapel
        $result = $scores->map(function ($item) {
            $mapel = \App\Models\DataMataPelajaran::where('kode_mapel', $item->kode_mapel)->first();
            return [
                'kode_mapel' => $item->kode_mapel,
                'nama_mapel' => $mapel->nama_mapel ?? '-',
                'nilai_harian' => $item->nilai_harian ?? 0,
                'nilai_uts' => $item->nilai_uts ?? 0,
                'nilai_uas' => $item->nilai_uas ?? 0,
                'nilai_akhir' => round($item->nilai_akhir_mapel ?? 0, 2),
                'nilai_rapor_tampil' => round($item->nilai_rapor_tampil ?? 0, 2),
                'status_ketuntasan' => $item->status_ketuntasan ?? '-',
            ];
        });

        return response()->json([
            'data' => $result->values(),
            'filters' => $validated,
        ]);
    }

    /**
     * Grafik perkembangan nilai semester ke semester.
     * 
     * Menampilkan riwayat nilai akhir per mata pelajaran di setiap semester,
     * cocok untuk line chart tren perkembangan akademik santri.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function scoresTrend(Request $request): JsonResponse
    {
        $nomorInduk = $request->user()->nomor_induk;

        $validated = $request->validate([
            'kode_mapel' => ['nullable', 'string', 'max:20'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
        ]);

        // Query nilai per semester, diurutkan dari semester awal
        $query = DataNilaiSiswa::where('nomor_induk', $nomorInduk)
            ->when(!empty($validated['kode_mapel']), 
                fn($q) => $q->where('kode_mapel', $validated['kode_mapel']))
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->select(
                'kode_mapel',
                'semester',
                'tahun_ajaran',
                'nilai_akhir_mapel',
                'status_ketuntasan'
            )
            ->orderBy('tahun_ajaran')
            ->orderBy('semester')
            ->get();

        // Group by mapel, kemudian sort semester
        $grouped = $query->groupBy('kode_mapel')->map(function ($items) {
            $mapel = \App\Models\DataMataPelajaran::where('kode_mapel', $items->first()->kode_mapel)->first();
            return [
                'kode_mapel' => $items->first()->kode_mapel,
                'nama_mapel' => $mapel->nama_mapel ?? '-',
                'trend' => $items->map(fn($item) => [
                    'semester' => $item->semester,
                    'tahun_ajaran' => $item->tahun_ajaran,
                    'nilai_akhir' => round($item->nilai_akhir_mapel ?? 0, 2),
                    'status_ketuntasan' => $item->status_ketuntasan ?? '-',
                ])->values()->toArray(),
            ];
        });

        return response()->json([
            'data' => $grouped->values(),
            'filters' => $validated,
        ]);
    }

    /**
     * Progress tracking akademik pribadi.
     * 
     * Perbandingan nilai santri terhadap KKM per mata pelajaran semester saat ini,
     * dan perubahan nilai dibanding semester sebelumnya (naik/turun/tetap).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function academicProgress(Request $request): JsonResponse
    {
        $nomorInduk = $request->user()->nomor_induk;

        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        // Default semester sekarang
        if (empty($validated['semester'])) {
            $latestSemester = DataNilaiSiswa::where('nomor_induk', $nomorInduk)
                ->orderByDesc('semester')
                ->value('semester');
            $validated['semester'] = $latestSemester ?? 1;
        }

        // Get nilai current semester
        $currentScores = DataNilaiSiswa::where('nomor_induk', $nomorInduk)
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->where('semester', $validated['semester'])
            ->select('kode_mapel', 'nilai_akhir_mapel', 'status_ketuntasan')
            ->get()
            ->keyBy('kode_mapel');

        // Get nilai semester sebelumnya untuk perbandingan
        $prevSemester = max($validated['semester'] - 1, 1);
        $prevScores = DataNilaiSiswa::where('nomor_induk', $nomorInduk)
            ->where('semester', $prevSemester)
            ->select('kode_mapel', 'nilai_akhir_mapel')
            ->get()
            ->keyBy('kode_mapel');

        // Get KKM per mapel
        $kkm = KkmMapel::where('semester', $validated['semester'])
            ->when(!empty($validated['tahun_ajaran']), 
                fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->select('kode_mapel', 'nilai_kkm')
            ->get()
            ->keyBy('kode_mapel');

        // Build result dengan perbandingan
        $result = $currentScores->map(function ($item) use ($kkm, $prevScores, $nomorInduk) {
            $mapel = \App\Models\DataMataPelajaran::where('kode_mapel', $item->kode_mapel)->first();
            $kkmValue = $kkm->get($item->kode_mapel)?->nilai_kkm ?? 0;
            $prevValue = $prevScores->get($item->kode_mapel)?->nilai_akhir_mapel ?? null;

            // Hitung perubahan
            $change = null;
            $changePercentage = null;
            if ($prevValue !== null) {
                $change = $item->nilai_akhir_mapel - $prevValue;
                $changePercentage = $prevValue > 0 
                    ? round(($change / $prevValue) * 100, 2) 
                    : ($change > 0 ? 100 : 0);
            }

            return [
                'kode_mapel' => $item->kode_mapel,
                'nama_mapel' => $mapel->nama_mapel ?? '-',
                'nilai_akhir' => round($item->nilai_akhir_mapel ?? 0, 2),
                'kkm' => round($kkmValue, 2),
                'tuntas' => ($item->nilai_akhir_mapel ?? 0) >= $kkmValue,
                'status_ketuntasan' => $item->status_ketuntasan ?? '-',
                'perubahan' => [
                    'nilai_sebelumnya' => $prevValue ? round($prevValue, 2) : null,
                    'selisih' => $change !== null ? round($change, 2) : null,
                    'persentase_perubahan' => $changePercentage,
                    'trend' => $change === null ? 'N/A' : ($change > 0 ? 'naik' : ($change < 0 ? 'turun' : 'tetap')),
                ],
            ];
        });

        // Summary
        $totalMapel = $result->count();
        $tuntasCount = $result->filter(fn($r) => $r['tuntas'])->count();
        $belumTuntasCount = $totalMapel - $tuntasCount;

        return response()->json([
            'data' => $result->values(),
            'summary' => [
                'total_mapel' => $totalMapel,
                'tuntas' => $tuntasCount,
                'belum_tuntas' => $belumTuntasCount,
                'persentase_tuntas' => $totalMapel > 0 ? round(($tuntasCount / $totalMapel) * 100, 2) : 0,
            ],
            'filters' => $validated,
        ]);
    }
}
