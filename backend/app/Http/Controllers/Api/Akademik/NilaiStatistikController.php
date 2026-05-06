<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataNilaiSiswa;
use App\Models\DataKelas;
use App\Models\KkmMapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NilaiStatistikController extends Controller
{
    /**
     * Statistik keseluruhan nilai santri (min, max, avg, count).
     * 
     * Query params:
     * - kode_kelas: filter by kelas
     * - kode_mapel: filter by mata pelajaran
     * - tahun_ajaran: filter by tahun ajaran
     * - semester: filter by semester (1 atau 2)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'kode_mapel' => ['nullable', 'string', 'max:20'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $query = DataNilaiSiswa::query();

        // Apply filters
        $query->when(!empty($validated['kode_kelas']), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['kode_mapel']), fn($q) => $q->where('kode_mapel', $validated['kode_mapel']))
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn($q) => $q->where('semester', $validated['semester']));

        $stats = $query->select(
            DB::raw('COALESCE(AVG(nilai_akhir_mapel), 0) as rata_rata'),
            DB::raw('MAX(nilai_akhir_mapel) as nilai_tertinggi'),
            DB::raw('MIN(nilai_akhir_mapel) as nilai_terendah'),
            DB::raw('COUNT(DISTINCT nomor_induk) as jumlah_santri'),
            DB::raw('COUNT(*) as total_nilai')
        )->first();

        return response()->json([
            'data' => [
                'rata_rata' => round($stats->rata_rata, 2),
                'nilai_tertinggi' => $stats->nilai_tertinggi,
                'nilai_terendah' => $stats->nilai_terendah,
                'jumlah_santri' => $stats->jumlah_santri,
                'total_nilai' => $stats->total_nilai,
            ],
            'filters' => $validated,
        ]);
    }

    /**
     * Rata-rata nilai per kelas.
     * 
     * Output: list kelas dengan rata-rata nilai, jumlah santri.
     * Cocok untuk bar chart / tabel perbandingan performa kelas.
     * 
     * Query params:
     * - tahun_ajaran: filter by tahun ajaran
     * - semester: filter by semester
     * - kode_mapel: filter by mata pelajaran (opsional)
     */
    public function averagePerClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'kode_mapel' => ['nullable', 'string', 'max:20'],
        ]);

        $query = DataNilaiSiswa::query()
            ->join('data_kelas', 'data_nilai_siswa.kode_kelas', '=', 'data_kelas.kode_kelas')
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('data_nilai_siswa.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn($q) => $q->where('data_nilai_siswa.semester', $validated['semester']))
            ->when(!empty($validated['kode_mapel']), fn($q) => $q->where('data_nilai_siswa.kode_mapel', $validated['kode_mapel']))
            ->groupBy('data_nilai_siswa.kode_kelas', 'data_kelas.nama_kelas')
            ->select(
                'data_nilai_siswa.kode_kelas',
                'data_kelas.nama_kelas',
                DB::raw('COALESCE(AVG(data_nilai_siswa.nilai_akhir_mapel), 0) as rata_rata'),
                DB::raw('COUNT(DISTINCT data_nilai_siswa.nomor_induk) as jumlah_santri')
            )
            ->orderByDesc('rata_rata');

        $data = $query->get();

        return response()->json([
            'data' => $data->map(fn($item) => [
                'kode_kelas' => $item->kode_kelas,
                'nama_kelas' => $item->nama_kelas,
                'rata_rata' => round($item->rata_rata, 2),
                'jumlah_santri' => $item->jumlah_santri,
            ]),
            'filters' => $validated,
        ]);
    }

    /**
     * Grafik perkembangan nilai per semester (trend).
     * 
     * Output: array per semester dengan nilai rata-rata.
     * Support filter: per santri, per kelas, per mata pelajaran.
     * Cocok untuk line chart.
     * 
     * Query params:
     * - nomor_induk: filter by santri (opsional)
     * - kode_kelas: filter by kelas (opsional)
     * - kode_mapel: filter by mata pelajaran (opsional)
     * - tahun_ajaran: filter by tahun ajaran (opsional)
     */
    public function trendPerSemester(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['nullable', 'string', 'max:20'],
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'kode_mapel' => ['nullable', 'string', 'max:20'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
        ]);

        $query = DataNilaiSiswa::query()
            ->when(!empty($validated['nomor_induk']), fn($q) => $q->where('nomor_induk', $validated['nomor_induk']))
            ->when(!empty($validated['kode_kelas']), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['kode_mapel']), fn($q) => $q->where('kode_mapel', $validated['kode_mapel']))
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->groupBy('semester')
            ->select(
                'semester',
                DB::raw('COALESCE(AVG(nilai_akhir_mapel), 0) as rata_rata'),
                DB::raw('MAX(nilai_akhir_mapel) as tertinggi'),
                DB::raw('MIN(nilai_akhir_mapel) as terendah'),
                DB::raw('COUNT(DISTINCT nomor_induk) as jumlah_santri')
            )
            ->orderBy('semester');

        $data = $query->get();

        return response()->json([
            'data' => $data->map(fn($item) => [
                'semester' => $item->semester,
                'rata_rata' => round($item->rata_rata, 2),
                'tertinggi' => $item->tertinggi,
                'terendah' => $item->terendah,
                'jumlah_santri' => $item->jumlah_santri,
            ]),
            'filters' => $validated,
        ]);
    }

    /**
     * Identifikasi santri berprestasi (nilai >= threshold).
     * 
     * Kriteria: nilai rata-rata >= threshold (default 85).
     * Output: list santri top performers dengan nilai detail per mapel.
     * 
     * Query params:
     * - kode_kelas: filter by kelas (opsional)
     * - tahun_ajaran: filter by tahun ajaran (opsional)
     * - semester: filter by semester (opsional)
     * - threshold: nilai minimum untuk berprestasi (default 85)
     * - limit: jumlah top performers yang ditampilkan (default 10)
     */
    public function topPerformers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $threshold = $validated['threshold'] ?? 85;
        $limit = $validated['limit'] ?? 10;

        // Get top performers grouped by santri with average nilai
        $topSantri = DataNilaiSiswa::query()
            ->when(!empty($validated['kode_kelas']), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn($q) => $q->where('semester', $validated['semester']))
            ->groupBy('nomor_induk')
            ->select(
                'nomor_induk',
                DB::raw('COALESCE(AVG(nilai_akhir_mapel), 0) as rata_rata_nilai')
            )
            ->havingRaw('COALESCE(AVG(nilai_akhir_mapel), 0) >= ?', [$threshold])
            ->orderByDesc('rata_rata_nilai')
            ->limit($limit)
            ->get();

        // Get detail per santri
        $santriIds = $topSantri->pluck('nomor_induk')->toArray();

        $details = DataNilaiSiswa::query()
            ->whereIn('nomor_induk', $santriIds)
            ->when(!empty($validated['kode_kelas']), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn($q) => $q->where('semester', $validated['semester']))
            ->orderBy('nomor_induk')
            ->get();

        $grouped = $details->groupBy('nomor_induk')->map(function ($items) {
            $first = $items->first();
            return [
                'nomor_induk' => $first->nomor_induk,
                'rata_rata' => round($items->avg('nilai_akhir_mapel'), 2),
                'mapel_count' => $items->count(),
                'nilai_detail' => $items->map(fn($item) => [
                    'kode_mapel' => $item->kode_mapel,
                    'nilai_akhir' => $item->nilai_akhir_mapel,
                    'nilai_tampil' => $item->nilai_rapor_tampil,
                    'status_ketuntasan' => $item->status_ketuntasan,
                ])->toArray(),
            ];
        });

        return response()->json([
            'data' => $grouped->values(),
            'count' => $grouped->count(),
            'filters' => [
                'threshold' => $threshold,
                'limit' => $limit,
                'kode_kelas' => $validated['kode_kelas'] ?? null,
                'tahun_ajaran' => $validated['tahun_ajaran'] ?? null,
                'semester' => $validated['semester'] ?? null,
            ],
        ]);
    }

    /**
     * Identifikasi santri yang perlu bimbingan (nilai < KKM atau rendah).
     * 
     * Kriteria: status_ketuntasan = "BELUM TUNTAS" atau nilai rata-rata < threshold.
     * Output: list santri dengan mapel yang kurang memuaskan.
     * 
     * Query params:
     * - kode_kelas: filter by kelas (opsional)
     * - tahun_ajaran: filter by tahun ajaran (opsional)
     * - semester: filter by semester (opsional)
     * - threshold: nilai minimum (default 65, jika di bawah ini perlu bimbingan)
     * - limit: jumlah santri yang ditampilkan (default 50)
     */
    public function needsHelp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $threshold = $validated['threshold'] ?? 65;
        $limit = $validated['limit'] ?? 50;

        // Get santri yang perlu bimbingan
        $needsHelpSantri = DataNilaiSiswa::query()
            ->when(!empty($validated['kode_kelas']), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn($q) => $q->where('semester', $validated['semester']))
            ->where(function ($q) use ($threshold) {
                $q->where('status_ketuntasan', 'BELUM TUNTAS')
                    ->orWhere('nilai_akhir_mapel', '<', $threshold);
            })
            ->groupBy('nomor_induk')
            ->select(
                'nomor_induk',
                DB::raw('COALESCE(AVG(nilai_akhir_mapel), 0) as rata_rata_nilai'),
                DB::raw("COUNT(CASE WHEN status_ketuntasan = 'BELUM TUNTAS' THEN 1 END) as mapel_belum_tuntas")
            )
            ->orderByDesc('mapel_belum_tuntas')
            ->orderBy('rata_rata_nilai')
            ->limit($limit)
            ->get();

        // Get detail per santri
        $santriIds = $needsHelpSantri->pluck('nomor_induk')->toArray();

        $details = DataNilaiSiswa::query()
            ->whereIn('nomor_induk', $santriIds)
            ->when(!empty($validated['kode_kelas']), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['tahun_ajaran']), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn($q) => $q->where('semester', $validated['semester']))
            ->where(function ($q) use ($threshold) {
                $q->where('status_ketuntasan', 'BELUM TUNTAS')
                    ->orWhere('nilai_akhir_mapel', '<', $threshold);
            })
            ->orderBy('nomor_induk')
            ->get();

        $grouped = $details->groupBy('nomor_induk')->map(function ($items) {
            $first = $items->first();
            $belumlulus = $items->filter(fn($item) => strtoupper(trim($item->status_ketuntasan)) === 'BELUM TUNTAS')->count();
            return [
                'nomor_induk' => $first->nomor_induk,
                'rata_rata' => round($items->avg('nilai_akhir_mapel'), 2),
                'mapel_perlu_bimbingan' => $items->count(),
                'mapel_belum_tuntas' => $belumlulus,
                'mapel_detail' => $items->map(fn($item) => [
                    'kode_mapel' => $item->kode_mapel,
                    'nilai_akhir' => $item->nilai_akhir_mapel,
                    'nilai_tampil' => $item->nilai_rapor_tampil,
                    'status_ketuntasan' => $item->status_ketuntasan,
                    'flag_warna' => $item->flag_warna_rapor,
                ])->toArray(),
            ];
        });

        return response()->json([
            'data' => $grouped->values(),
            'count' => $grouped->count(),
            'filters' => [
                'threshold' => $threshold,
                'limit' => $limit,
                'kode_kelas' => $validated['kode_kelas'] ?? null,
                'tahun_ajaran' => $validated['tahun_ajaran'] ?? null,
                'semester' => $validated['semester'] ?? null,
            ],
        ]);
    }
}
