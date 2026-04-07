<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataKelas;
use App\Models\DataKonversiNilai;
use App\Models\DataRaport;
use App\Models\DataSantri;
use App\Models\NilaiAkhlak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RaportGenerateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'nama' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'in:DRAFT,TERBIT'],
            'nomor_induk' => ['nullable', 'string', 'max:20'],
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'include_nilai_mapel' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);

        $query = DataRaport::query()
            ->leftJoin('data_santri as santri', 'santri.nomor_induk', '=', 'data_raport.nomor_induk')
            ->select([
                'data_raport.*',
                'santri.nama_lengkap_santri',
            ])
            ->when(array_key_exists('status', $validated), fn($q) => $q->where('data_raport.status_raport', strtoupper((string) $validated['status'])))
            ->when(array_key_exists('nomor_induk', $validated), fn($q) => $q->where('data_raport.nomor_induk', $validated['nomor_induk']))
            ->when(array_key_exists('kode_kelas', $validated), fn($q) => $q->where('data_raport.kode_kelas', $validated['kode_kelas']))
            ->when(array_key_exists('tahun_ajaran', $validated), fn($q) => $q->where('data_raport.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(array_key_exists('semester', $validated), fn($q) => $q->where('data_raport.semester', (int) $validated['semester']))
            ->when(array_key_exists('nama', $validated), fn($q) => $q->where('santri.nama_lengkap_santri', 'like', '%' . $validated['nama'] . '%'))
            ->when(array_key_exists('q', $validated), function ($q) use ($validated) {
                $keyword = (string) $validated['q'];

                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('data_raport.nomor_induk', 'like', '%' . $keyword . '%')
                        ->orWhere('data_raport.kode_kelas', 'like', '%' . $keyword . '%')
                        ->orWhere('santri.nama_lengkap_santri', 'like', '%' . $keyword . '%');
                });
            })
            ->orderByDesc('data_raport.tahun_ajaran')
            ->orderByDesc('data_raport.semester')
            ->orderBy('santri.nama_lengkap_santri');

        $result = $query->paginate($perPage);

        if (($validated['include_nilai_mapel'] ?? false) === true) {
            $result->setCollection(
                $result->getCollection()->map(function ($row) {
                    $row->nilai_mapel = $this->buildNilaiMapelWithKonversi(
                        nomorInduk: (string) $row->nomor_induk,
                        tahunAjaran: (string) $row->tahun_ajaran,
                        semester: (int) $row->semester,
                        kodeKelas: (string) $row->kode_kelas
                    );

                    return $row;
                })
            );
        }

        return response()->json($result);
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $raport = DataRaport::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', (int) $validated['semester'])
            ->firstOrFail();

        $santri = DataSantri::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->firstOrFail();

        $nilaiAkhlak = NilaiAkhlak::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', (int) $validated['semester'])
            ->orderBy('aspek')
            ->get(['aspek', 'nilai_angka', 'deskripsi']);

        $nilaiMapel = $this->buildNilaiMapelWithKonversi(
            nomorInduk: $validated['nomor_induk'],
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester'],
            kodeKelas: (string) $raport->kode_kelas
        );

        return response()->json([
            'message' => 'Detail rapor berhasil diambil.',
            'data' => [
                'raport' => $raport,
                'santri' => $santri,
                'nilai_mapel' => $nilaiMapel,
                'nilai_akhlak' => $nilaiAkhlak,
            ],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $santri = DataSantri::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->firstOrFail();

        $nilaiRows = DB::table('data_nilai_siswa')
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->whereNotNull('nilai_rapor_tampil')
            ->get(['kode_mapel', 'nilai_rapor_tampil', 'flag_warna_rapor']);

        $jumlahNilai = (float) $nilaiRows->sum(fn($row) => (float) $row->nilai_rapor_tampil);
        $rataRataMapel = $nilaiRows->count() > 0
            ? $this->roundHalfUp($jumlahNilai / $nilaiRows->count(), 2)
            : 0.0;

        $absensi = $this->buildAbsensiSummary(
            nomorInduk: $validated['nomor_induk'],
            kodeKelas: (string) $santri->kode_kelas,
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $akhlakRows = NilaiAkhlak::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->orderBy('aspek')
            ->get(['aspek', 'nilai_angka', 'deskripsi']);

        $akhlakRataRata = $akhlakRows->count() > 0
            ? $this->roundHalfUp((float) $akhlakRows->avg('nilai_angka'), 2)
            : null;

        $existing = DataRaport::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->first();

        $raport = DataRaport::updateOrCreate(
            [
                'nomor_induk' => $validated['nomor_induk'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            [
                'kode_kelas' => $santri->kode_kelas,
                'jumlah_nilai' => $this->roundHalfUp($jumlahNilai, 2),
                'rata_rata' => $rataRataMapel,
                'hadir' => $absensi['hadir'],
                'sakit' => $absensi['sakit'],
                'izin' => $absensi['izin'],
                'alpha' => $absensi['alpha'],
                'keseharian_kebersihan' => $existing?->keseharian_kebersihan,
                'keseharian_kerapian' => $existing?->keseharian_kerapian,
                'keseharian_keterampilan' => $existing?->keseharian_keterampilan,
                'catatan_wali' => $existing?->catatan_wali,
                'id_wali_kelas' => $existing?->id_wali_kelas,
                'status_raport' => 'DRAFT',
            ]
        );

        return response()->json([
            'message' => 'Rekap raport berhasil digenerate (DRAFT).',
            'data' => $raport,
            'rekap' => [
                'nilai_mapel_count' => $nilaiRows->count(),
                'jumlah_nilai' => $this->roundHalfUp($jumlahNilai, 2),
                'rata_rata_rapor' => $rataRataMapel,
                'absensi' => $absensi,
                'nilai_akhlak' => [
                    'rata_rata' => $akhlakRataRata,
                    'items' => $akhlakRows,
                ],
            ],
        ]);
    }

    public function rank(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $santriRows = DataSantri::query()
            ->where('kode_kelas', $validated['kode_kelas'])
            ->orderBy('nomor_induk')
            ->get(['nomor_induk', 'nama_lengkap_santri']);

        if ($santriRows->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada santri pada kelas ini.',
            ], 422);
        }

        $scores = [];

        foreach ($santriRows as $santri) {
            $nilaiRows = DB::table('data_nilai_siswa as ns')
                ->join('data_mata_pelajaran as mp', 'mp.kode_mapel', '=', 'ns.kode_mapel')
                ->where('ns.nomor_induk', $santri->nomor_induk)
                ->where('ns.tahun_ajaran', $validated['tahun_ajaran'])
                ->where('ns.semester', $validated['semester'])
                ->whereNotNull('ns.nilai_rapor_tampil')
                ->get(['ns.nilai_rapor_tampil', 'mp.kelompok_mapel', 'mp.nama_mapel']);

            $hifzhValues = [];
            $diniyyahValues = [];
            $umumValues = [];

            foreach ($nilaiRows as $row) {
                $nilai = (float) $row->nilai_rapor_tampil;
                $group = $this->classifyMapelGroup((string) ($row->kelompok_mapel ?? ''), (string) ($row->nama_mapel ?? ''));

                if ($group === 'hifzh') {
                    $hifzhValues[] = $nilai;
                } elseif ($group === 'diniyyah') {
                    $diniyyahValues[] = $nilai;
                } else {
                    $umumValues[] = $nilai;
                }
            }

            $nilaiHifzh = $this->averageFloat($hifzhValues);
            $rataDiniyyah = $this->averageFloat($diniyyahValues);
            $rataUmum = $this->averageFloat($umumValues);

            $rankingRaw = (($nilaiHifzh * 2) + ($rataDiniyyah * 2) + ($rataUmum * 1)) / 5;
            $rankingScore = $this->roundHalfUp($rankingRaw, 2);

            DataRaport::updateOrCreate(
                [
                    'nomor_induk' => $santri->nomor_induk,
                    'tahun_ajaran' => $validated['tahun_ajaran'],
                    'semester' => $validated['semester'],
                ],
                [
                    'kode_kelas' => $validated['kode_kelas'],
                    'rata_rata' => $rankingScore,
                    'status_raport' => 'DRAFT',
                ]
            );

            $scores[] = [
                'nomor_induk' => $santri->nomor_induk,
                'nama_lengkap' => $santri->nama_lengkap_santri,
                'nilai_hifzh' => $nilaiHifzh,
                'rata_diniyyah' => $rataDiniyyah,
                'rata_umum' => $rataUmum,
                'ranking_score' => $rankingScore,
            ];
        }

        usort($scores, function (array $a, array $b): int {
            if ($a['ranking_score'] === $b['ranking_score']) {
                return $b['nilai_hifzh'] <=> $a['nilai_hifzh'];
            }

            return $b['ranking_score'] <=> $a['ranking_score'];
        });

        $total = count($scores);
        $topLimit = $total > 10 ? 10 : 5;

        foreach ($scores as $index => $row) {
            DataRaport::query()
                ->where('nomor_induk', $row['nomor_induk'])
                ->where('tahun_ajaran', $validated['tahun_ajaran'])
                ->where('semester', $validated['semester'])
                ->update([
                    'peringkat_kelas' => $index + 1,
                    'total_siswa_kelas' => $total,
                    'status_raport' => 'DRAFT',
                ]);
        }

        return response()->json([
            'message' => 'Ranking kelas berhasil dihitung.',
            'data' => [
                'kode_kelas' => $validated['kode_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => (int) $validated['semester'],
                'total_siswa' => $total,
                'tampilan_top' => min($topLimit, $total),
                'ranking' => $scores,
            ],
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'nomor_induk' => ['nullable', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tanggal_terbit' => ['nullable', 'date'],
        ]);

        $query = DataRaport::query()
            ->where('kode_kelas', $validated['kode_kelas'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester']);

        if (! empty($validated['nomor_induk'])) {
            $query->where('nomor_induk', $validated['nomor_induk']);
        }

        $updated = $query->update([
            'status_raport' => 'TERBIT',
            'tanggal_terbit' => $validated['tanggal_terbit'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Status raport berhasil diterbitkan.',
            'data' => [
                'total_terupdate' => $updated,
                'kode_kelas' => $validated['kode_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => (int) $validated['semester'],
                'nomor_induk' => $validated['nomor_induk'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? now()->toDateString(),
            ],
        ]);
    }

    private function buildAbsensiSummary(string $nomorInduk, string $kodeKelas, string $tahunAjaran, int $semester): array
    {
        $summary = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 's.id_sesi', '=', 'a.id_sesi')
            ->join('jadwal_pembelajaran as j', 'j.id_jadwal', '=', 's.id_jadwal')
            ->join('data_kelas_mapel as km', 'km.id_kelas_mapel', '=', 'j.id_kelas_mapel')
            ->where('a.nomor_induk', $nomorInduk)
            ->where('km.kode_kelas', $kodeKelas)
            ->where('km.tahun_ajaran', $tahunAjaran)
            ->where('km.semester', $semester)
            ->selectRaw("SUM(CASE WHEN LOWER(a.status_kehadiran) = 'hadir' THEN 1 ELSE 0 END) as hadir")
            ->selectRaw("SUM(CASE WHEN LOWER(a.status_kehadiran) = 'sakit' THEN 1 ELSE 0 END) as sakit")
            ->selectRaw("SUM(CASE WHEN LOWER(a.status_kehadiran) IN ('izin','ijin') THEN 1 ELSE 0 END) as izin")
            ->selectRaw("SUM(CASE WHEN LOWER(a.status_kehadiran) IN ('alpha','alpa','tanpa_keterangan') THEN 1 ELSE 0 END) as alpha")
            ->first();

        return [
            'hadir' => (int) ($summary->hadir ?? 0),
            'sakit' => (int) ($summary->sakit ?? 0),
            'izin' => (int) ($summary->izin ?? 0),
            'alpha' => (int) ($summary->alpha ?? 0),
        ];
    }

    private function classifyMapelGroup(string $kelompokMapel, string $namaMapel): string
    {
        $group = strtolower(trim($kelompokMapel));
        $name = strtolower(trim($namaMapel));

        if (str_contains($group, 'hifzh') || str_contains($group, 'hafalan') || str_contains($name, 'hafalan')) {
            return 'hifzh';
        }

        if (str_contains($group, 'diniyyah') || str_contains($group, 'agama')) {
            return 'diniyyah';
        }

        return 'umum';
    }

    private function buildNilaiMapelWithKonversi(string $nomorInduk, string $tahunAjaran, int $semester, string $kodeKelas)
    {
        $nilaiMapel = DB::table('data_nilai_siswa as ns')
            ->leftJoin('data_mata_pelajaran as mp', 'mp.kode_mapel', '=', 'ns.kode_mapel')
            ->where('ns.nomor_induk', $nomorInduk)
            ->where('ns.tahun_ajaran', $tahunAjaran)
            ->where('ns.semester', $semester)
            ->select([
                'ns.kode_mapel',
                'mp.nama_mapel',
                'mp.kelompok_mapel',
                'ns.nilai_harian',
                'ns.nilai_uts',
                'ns.nilai_uas',
                'ns.nilai_akhir_mapel',
                'ns.nilai_rapor_tampil',
                'ns.flag_warna_rapor',
            ])
            ->orderBy('mp.urutan')
            ->orderBy('mp.nama_mapel')
            ->get();

        $konversiRows = $this->resolveKonversiRowsForKelas(
            kodeKelas: $kodeKelas,
            tahunAjaran: $tahunAjaran
        );

        return $nilaiMapel->map(function ($row) use ($konversiRows) {
            $konversi = $this->matchKonversiNilai((float) ($row->nilai_rapor_tampil ?? 0), $konversiRows);

            $row->nilai_huruf = $konversi['nilai_huruf'];
            $row->predikat = $konversi['predikat'];

            return $row;
        });
    }

    private function resolveKonversiRowsForKelas(string $kodeKelas, string $tahunAjaran)
    {
        $kodeUnit = DataKelas::query()
            ->where('kode_kelas', $kodeKelas)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderByDesc('id_kelas')
            ->value('kode_unit');

        $query = DataKonversiNilai::query()
            ->where('status', 'AKTIF');

        if ($kodeUnit !== null) {
            $query->where(function ($q) use ($kodeUnit) {
                $q->where('kode_unit', $kodeUnit)
                    ->orWhereNull('kode_unit');
            });
        } else {
            $query->whereNull('kode_unit');
        }

        return $query
            ->orderByRaw('CASE WHEN kode_unit IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('nilai_min')
            ->orderByDesc('id_konversi')
            ->get(['kode_unit', 'nilai_min', 'nilai_max', 'nilai_huruf', 'predikat']);
    }

    private function matchKonversiNilai(float $nilai, $konversiRows): array
    {
        foreach ($konversiRows as $row) {
            if ($nilai >= (float) $row->nilai_min && $nilai <= (float) $row->nilai_max) {
                return [
                    'nilai_huruf' => $row->nilai_huruf,
                    'predikat' => $row->predikat,
                ];
            }
        }

        return [
            'nilai_huruf' => null,
            'predikat' => null,
        ];
    }

    /**
     * @param array<int, float> $values
     */
    private function averageFloat(array $values): float
    {
        if (count($values) === 0) {
            return 0.0;
        }

        return $this->roundHalfUp(array_sum($values) / count($values), 2);
    }

    private function roundHalfUp(float $value, int $precision): float
    {
        $factor = 10 ** $precision;

        return floor(($value * $factor) + 0.5) / $factor;
    }
}
