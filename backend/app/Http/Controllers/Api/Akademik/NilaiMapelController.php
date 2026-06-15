<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\BobotNilai;
use App\Models\DataKelas;
use App\Models\DataNilaiSiswa;
use App\Models\DataRaport;
use App\Models\DataSantri;
use App\Models\KkmMapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NilaiMapelController extends Controller
{
    private const ALLOWED_TUGAS_TYPES = [
        'PR',
        'TUGAS_PENGGANTI',
        'MODUL_KOMPETENSI',
    ];

    /**
     * List Nilai Mapel per Santri
     *
     * Mengambil daftar nilai mata pelajaran untuk seorang santri secara spesifik berdasarkan nomor induk.
     * Mendukung pagination dan filter berdasarkan kode mapel, kode kelas, tahun ajaran, dan semester.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'kode_mapel' => ['nullable', 'string', 'max:20'],
            'kode_kelas' => ['nullable', 'string', 'max:10'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $query = DataNilaiSiswa::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->when(array_key_exists('kode_mapel', $validated), fn($q) => $q->where('kode_mapel', $validated['kode_mapel']))
            ->when(array_key_exists('kode_kelas', $validated), fn($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(array_key_exists('tahun_ajaran', $validated), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(array_key_exists('semester', $validated), fn($q) => $q->where('semester', (int) $validated['semester']))
            ->orderByDesc('id_nilai');

        return response()->json($query->paginate($perPage));
    }

    /**
     * List Nilai Mapel Kelas
     *
     * Menampilkan daftar seluruh santri yang aktif beserta data nilainya dalam satu kelas.
     * Digunakan oleh pengajar untuk melihat rekap nilai satu kelas pada mapel tertentu.
     */
    public function kelasIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kode_mapel' => ['required', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $santris = DataSantri::query()
            ->where('kode_kelas', $validated['kode_kelas'])
            ->where(function($q) {
                $q->where('status', 'AKTIF')
                  ->orWhere('status', 'Aktif');
            })
            ->where(function($q) {
                $q->whereNull('is_deleted')
                  ->orWhere('is_deleted', false)
                  ->orWhere('is_deleted', 0);
            })
            ->orderBy('nama_lengkap_santri')
            ->get();

        $nilais = DataNilaiSiswa::query()
            ->where('kode_kelas', $validated['kode_kelas'])
            ->where('kode_mapel', $validated['kode_mapel'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', (int) $validated['semester'])
            ->get()
            ->keyBy('nomor_induk');

        $result = $santris->map(function ($santri) use ($nilais) {
            $nilai = $nilais->get($santri->nomor_induk);
            
            return [
                'id' => $santri->id_santri,
                'nomor_induk' => $santri->nomor_induk,
                'nama_santri' => $santri->nama_lengkap_santri,
                'nilai' => $nilai ? $nilai->toArray() : null,
            ];
        });

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Detail Nilai Mapel
     *
     * Mengambil detail satu record nilai mata pelajaran berdasarkan kode mapel dan nomor induk santri.
     * Mengembalikan data nilai terbaru (diurutkan berdasarkan tahun ajaran dan semester secara descending).
     */
    public function show(Request $request, string $kode_mapel): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $nilai = DataNilaiSiswa::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('kode_mapel', $kode_mapel)
            ->when(array_key_exists('tahun_ajaran', $validated), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(array_key_exists('semester', $validated), fn($q) => $q->where('semester', (int) $validated['semester']))
            ->orderByDesc('tahun_ajaran')
            ->orderByDesc('semester')
            ->firstOrFail();

        return response()->json([
            'data' => $nilai,
        ]);
    }

    /**
     * Simpan Nilai Mapel (Upsert)
     *
     * Menyimpan komponen nilai mapel santri (Tugas, Ulangan, Ujian Akhir) dan otomatis 
     * menghitung persentase/bobot untuk mendapatkan nilai akhir, nilai rapor, dan status ketuntasan (KKM).
     * Apabila data nilai pada semester dan tahun ajaran tersebut sudah ada, maka akan diupdate (Upsert).
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'kode_mapel' => ['required', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'id_petugas_input' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'keterangan' => ['nullable', 'string'],

            'tugas' => ['required', 'array', 'min:3'],
            'tugas.*.nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'tugas.*.jenis' => ['required', 'string', 'in:PR,TUGAS_PENGGANTI,MODUL_KOMPETENSI'],

            'ulangan' => ['required', 'array', 'min:3'],
            'ulangan.*.nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'ulangan.*.soal_disusun_pengajar' => ['required', 'boolean'],
            'ulangan.*.diawasi_pengajar' => ['required', 'boolean'],

            'ujian_akhir' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $santri = DataSantri::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->firstOrFail();

        if ((string) $santri->kode_kelas !== (string) $validated['kode_kelas']) {
            return response()->json([
                'message' => 'kode_kelas tidak sesuai dengan data santri berdasarkan nomor_induk.',
            ], 422);
        }

        // Cek jika raport sudah TERBIT, tidak boleh input nilai lagi
        $raportTerbit = DataRaport::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->where('status_raport', 'TERBIT')
            ->exists();

        if ($raportTerbit) {
            return response()->json([
                'message' => 'Tidak bisa input nilai karena raport sudah terbit (TERBIT). Silakan tarik raport terlebih dahulu jika ingin mengubah nilai.',
            ], 403);
        }

        $nilaiTugas = $this->averageComponent($validated['tugas']);

        $ulanganValid = $this->filterValidUlangan($validated['ulangan']);
        if (count($ulanganValid) < 3) {
            return response()->json([
                'message' => 'Minimal 3 nilai ulangan valid (soal_disusun_pengajar=true dan diawasi_pengajar=true).',
            ], 422);
        }

        $nilaiUlangan = $this->averageComponent($ulanganValid);
        $nilaiUjianAkhir = (float) $validated['ujian_akhir'];

        $bobot = $this->resolveBobotGlobal(
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        if (! $bobot) {
            return response()->json([
                'message' => 'Bobot nilai belum diset untuk tahun ajaran dan semester ini. Silakan set bobot terlebih dahulu.',
            ], 422);
        }

        $bobotTugas = ((float) $bobot->bobot_harian) / 100;
        $bobotUlangan = ((float) $bobot->bobot_uts) / 100;
        $bobotUjianAkhir = ((float) $bobot->bobot_uas) / 100;

        $nilaiAkhirRaw =
            ($nilaiTugas * $bobotTugas)
            + ($nilaiUlangan * $bobotUlangan)
            + ($nilaiUjianAkhir * $bobotUjianAkhir);

        $nilaiAkhirMentah = $nilaiAkhirRaw;
        $nilaiRaporBulat = $this->roundRaporInteger($nilaiAkhirMentah);

        [$nilaiRaporTampil, $flagWarnaRapor] = $this->normalizeNilaiRapor(
            nilaiAkhirMentah: $nilaiAkhirMentah,
            nilaiRaporBulat: $nilaiRaporBulat
        );

        $kkm = $this->resolveKkm(
            kodeMapel: $validated['kode_mapel'],
            kodeKelas: $validated['kode_kelas'],
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $statusKetuntasan = $kkm?->statusKetuntasan((float) $nilaiAkhirMentah);

        $nilaiDetail = sprintf(
            'Tugas:[%s];Ulangan:[%s];UjianAkhir:%s;NilaiAkhirMapel:%s',
            implode(',', array_map(fn($item) => number_format((float) $item['nilai'], 2, '.', ''), $validated['tugas'])),
            implode(',', array_map(fn($item) => number_format((float) $item['nilai'], 2, '.', ''), $validated['ulangan'])),
            number_format((float) $validated['ujian_akhir'], 2, '.', ''),
            number_format($this->roundHalfUp($nilaiAkhirMentah, 2), 2, '.', '')
        );

        $nilai = DataNilaiSiswa::updateOrCreate(
            [
                'nomor_induk' => $validated['nomor_induk'],
                'kode_mapel' => $validated['kode_mapel'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            [
                'kode_kelas' => $validated['kode_kelas'],
                'nilai_harian' => $this->roundHalfUp($nilaiTugas, 2),
                'nilai_uts' => $this->roundHalfUp($nilaiUlangan, 2),
                'nilai_uas' => $this->roundHalfUp($nilaiUjianAkhir, 2),
                'nilai_akhir_mapel' => $this->roundHalfUp($nilaiAkhirMentah, 2),
                'nilai_rapor_tampil' => $nilaiRaporTampil,
                'flag_warna_rapor' => $flagWarnaRapor,
                'status_ketuntasan' => $statusKetuntasan,
                'keterangan' => $validated['keterangan'] ?? null,
                'nilai_detail' => $nilaiDetail,
                'id_petugas_input' => $validated['id_petugas_input'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Komponen nilai mapel berhasil disimpan.',
            'data' => $nilai,
            'perhitungan' => [
                'kebijakan_bobot' => [
                    'tugas' => (float) $bobot->bobot_harian,
                    'ulangan' => (float) $bobot->bobot_uts,
                    'ujian_akhir' => (float) $bobot->bobot_uas,
                ],
                'kriteria_tugas_diizinkan' => self::ALLOWED_TUGAS_TYPES,
                'rata_rata_tugas' => $nilaiTugas,
                'rata_rata_ulangan' => $nilaiUlangan,
                'nilai_ujian_akhir' => $this->roundHalfUp($nilaiUjianAkhir, 2),
                'jumlah_ulangan_dihitung' => count($ulanganValid),
                'nilai_akhir_mentah' => $nilaiAkhirMentah,
                'nilai_akhir_mapel' => $this->roundHalfUp($nilaiAkhirMentah, 2),
                'nilai_rapor' => $nilaiRaporBulat,
                'nilai_rapor_tampil' => $nilaiRaporTampil,
                'flag_warna_rapor' => $flagWarnaRapor,
                'kkm' => [
                    'nilai_kkm' => $kkm ? (float) $kkm->nilai_kkm : null,
                    'status' => $statusKetuntasan,
                ],
            ],
        ]);
    }

    /**
     * Update Nilai Mapel
     *
     * Memperbarui komponen nilai mapel santri berdasarkan ID nilai yang spesifik.
     * Berlaku validasi: jika raport sudah diterbitkan (status TERBIT), maka nilai tidak dapat diubah.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'kode_mapel' => ['required', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'id_petugas_input' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'keterangan' => ['nullable', 'string'],

            'tugas' => ['required', 'array', 'min:3'],
            'tugas.*.nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'tugas.*.jenis' => ['required', 'string', 'in:PR,TUGAS_PENGGANTI,MODUL_KOMPETENSI'],

            'ulangan' => ['required', 'array', 'min:3'],
            'ulangan.*.nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'ulangan.*.soal_disusun_pengajar' => ['required', 'boolean'],
            'ulangan.*.diawasi_pengajar' => ['required', 'boolean'],

            'ujian_akhir' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $nilai = DataNilaiSiswa::findOrFail($id);

        // sanity: nomor_induk and kode_mapel must match the existing record
        if ($nilai->nomor_induk !== $validated['nomor_induk'] || $nilai->kode_mapel !== $validated['kode_mapel']) {
            return response()->json([
                'message' => 'Nomor_induk atau kode_mapel tidak sesuai dengan record yang dimaksud.',
            ], 422);
        }

        $santri = DataSantri::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->firstOrFail();

        if ((string) $santri->kode_kelas !== (string) $validated['kode_kelas']) {
            return response()->json([
                'message' => 'kode_kelas tidak sesuai dengan data santri berdasarkan nomor_induk.',
            ], 422);
        }

        // Cek jika raport sudah TERBIT, tidak boleh edit nilai
        $raportTerbit = DataRaport::query()
            ->where('nomor_induk', $validated['nomor_induk'])
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->where('status_raport', 'TERBIT')
            ->exists();

        if ($raportTerbit) {
            return response()->json([
                'message' => 'Tidak bisa mengubah nilai karena raport sudah terbit (TERBIT). Silakan tarik raport terlebih dahulu jika ingin mengubah nilai.',
            ], 403);
        }

        $nilaiTugas = $this->averageComponent($validated['tugas']);

        $ulanganValid = $this->filterValidUlangan($validated['ulangan']);
        if (count($ulanganValid) < 3) {
            return response()->json([
                'message' => 'Minimal 3 nilai ulangan valid (soal_disusun_pengajar=true dan diawasi_pengajar=true).',
            ], 422);
        }

        $nilaiUlangan = $this->averageComponent($ulanganValid);
        $nilaiUjianAkhir = (float) $validated['ujian_akhir'];

        $bobot = $this->resolveBobotGlobal(
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        if (! $bobot) {
            return response()->json([
                'message' => 'Bobot nilai belum diset untuk tahun ajaran dan semester ini. Silakan set bobot terlebih dahulu.',
            ], 422);
        }

        $bobotTugas = ((float) $bobot->bobot_harian) / 100;
        $bobotUlangan = ((float) $bobot->bobot_uts) / 100;
        $bobotUjianAkhir = ((float) $bobot->bobot_uas) / 100;

        $nilaiAkhirRaw =
            ($nilaiTugas * $bobotTugas)
            + ($nilaiUlangan * $bobotUlangan)
            + ($nilaiUjianAkhir * $bobotUjianAkhir);

        $nilaiAkhirMentah = $nilaiAkhirRaw;
        $nilaiRaporBulat = $this->roundRaporInteger($nilaiAkhirMentah);

        [$nilaiRaporTampil, $flagWarnaRapor] = $this->normalizeNilaiRapor(
            nilaiAkhirMentah: $nilaiAkhirMentah,
            nilaiRaporBulat: $nilaiRaporBulat
        );

        $kkm = $this->resolveKkm(
            kodeMapel: $validated['kode_mapel'],
            kodeKelas: $validated['kode_kelas'],
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $statusKetuntasan = $kkm?->statusKetuntasan((float) $nilaiAkhirMentah);

        $nilaiDetail = sprintf(
            'Tugas:[%s];Ulangan:[%s];UjianAkhir:%s;NilaiAkhirMapel:%s',
            implode(',', array_map(fn($item) => number_format((float) $item['nilai'], 2, '.', ''), $validated['tugas'])),
            implode(',', array_map(fn($item) => number_format((float) $item['nilai'], 2, '.', ''), $validated['ulangan'])),
            number_format((float) $validated['ujian_akhir'], 2, '.', ''),
            number_format($this->roundHalfUp($nilaiAkhirMentah, 2), 2, '.', '')
        );

        $nilai->update([
            'kode_kelas' => $validated['kode_kelas'],
            'nilai_harian' => $this->roundHalfUp($nilaiTugas, 2),
            'nilai_uts' => $this->roundHalfUp($nilaiUlangan, 2),
            'nilai_uas' => $this->roundHalfUp($nilaiUjianAkhir, 2),
            'nilai_akhir_mapel' => $this->roundHalfUp($nilaiAkhirMentah, 2),
            'nilai_rapor_tampil' => $nilaiRaporTampil,
            'flag_warna_rapor' => $flagWarnaRapor,
            'status_ketuntasan' => $statusKetuntasan,
            'keterangan' => $validated['keterangan'] ?? null,
            'nilai_detail' => $nilaiDetail,
            'id_petugas_input' => $validated['id_petugas_input'] ?? null,
        ]);

        return response()->json([
            'message' => 'Nilai mapel berhasil diperbarui.',
            'data' => $nilai,
        ]);
    }

    /**
     * Hapus Nilai Mapel
     *
     * Menghapus record nilai mata pelajaran dari database berdasarkan ID nilai spesifik.
     */
    public function destroy(int $id): JsonResponse
    {
        $nilai = DataNilaiSiswa::findOrFail($id);
        $nilai->delete();

        return response()->json([
            'message' => 'Nilai mapel berhasil dihapus.',
        ]);
    }

    /**
     * @param array<int, array{nilai: mixed}> $rows
     */
    private function averageComponent(array $rows): float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += (float) $row['nilai'];
        }

        return $total / max(count($rows), 1);
    }

    /**
     * @param array<int, array{nilai: mixed, soal_disusun_pengajar: bool, diawasi_pengajar: bool}> $ulanganRows
     * @return array<int, array{nilai: mixed}>
     */
    private function filterValidUlangan(array $ulanganRows): array
    {
        return array_values(array_filter($ulanganRows, function (array $row): bool {
            return ($row['soal_disusun_pengajar'] ?? false) === true
                && ($row['diawasi_pengajar'] ?? false) === true;
        }));
    }

    /**
     * Pembulatan half-up agar sesuai aturan client (1-4 turun, 5-9 naik).
     */
    private function roundHalfUp(float $value, int $precision): float
    {
        $factor = 10 ** $precision;

        return floor(($value * $factor) + 0.5) / $factor;
    }

    private function roundRaporInteger(float $nilai): int
    {
        $desimal = $nilai - floor($nilai);

        return $desimal >= 0.5 ? (int) ceil($nilai) : (int) floor($nilai);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function normalizeNilaiRapor(float $nilaiAkhirMentah, int $nilaiRaporBulat): array
    {
        if ($nilaiRaporBulat > 98) {
            $nilaiRaporBulat = 98;
        }

        // Warna tinta mengikuti nilai asli/mentah: nilai asli < 50 wajib merah.
        if ($nilaiAkhirMentah < 50 || $nilaiRaporBulat < 50) {
            return [50, 'MERAH'];
        }

        return [$nilaiRaporBulat, 'HITAM'];
    }

    private function resolveBobotGlobal(string $tahunAjaran, int $semester): ?BobotNilai
    {
        return BobotNilai::query()
            ->global()
            ->whereNull('kode_unit')
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderByDesc('id_bobot')
            ->first();
    }

    private function resolveKkm(string $kodeMapel, string $kodeKelas, string $tahunAjaran, int $semester): ?KkmMapel
    {
        $kelas = DataKelas::query()->where('kode_kelas', $kodeKelas)->first();

        if (! $kelas) {
            return null;
        }

        $kodeUnit = $kelas->kode_unit;

        return KkmMapel::query()
            ->where('kode_mapel', $kodeMapel)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->where(function ($query) use ($kodeUnit) {
                $query->where('kode_unit', $kodeUnit)
                    ->orWhereNull('kode_unit');
            })
            ->orderByRaw('CASE WHEN kode_unit IS NULL THEN 1 ELSE 0 END')
            ->first();
    }
}
