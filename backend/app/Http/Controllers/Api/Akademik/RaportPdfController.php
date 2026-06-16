<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
use App\Models\DataKelas;
use App\Models\DataKonversiNilai;
use App\Models\DataPetugas;
use App\Models\DataRaport;
use App\Models\DataSantri;
use App\Models\LogDownloadRaport;
use App\Models\NilaiAkhlak;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RaportPdfController extends Controller
{
    public function download(Request $request): Response
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $payload = $this->buildRaportPayload(
            nomorInduk: $validated['nomor_induk'],
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $filename = $this->buildFilename(
            nomorInduk: $validated['nomor_induk'],
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $this->logDownload(
            request: $request,
            raport: $payload['raport'],
            filename: $filename,
            tipePengunduh: 'PETUGAS',
            idPetugas: $this->resolvePetugasId(),
            keterangan: 'Unduh PDF rapor oleh petugas.'
        );

        return Pdf::loadView('pdf.raport', $payload)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function selfShow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $santriUser = $this->resolveAuthenticatedSantri();
        if (! $santriUser) {
            return response()->json([
                'message' => 'Akses hanya untuk akun santri.',
            ], 403);
        }

        $payload = $this->buildRaportPayload(
            nomorInduk: (string) $santriUser->nomor_induk,
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        return response()->json([
            'message' => 'Rapor berhasil diambil.',
            'data' => [
                'raport' => $payload['raport'],
                'santri' => $payload['santri'],
                'nilai_mapel' => $payload['nilaiMapel'],
                'nilai_akhlak' => $payload['nilaiAkhlak'],
                'nilai_akhlak_ringkas' => $payload['nilaiAkhlakRingkas'],
            ],
        ]);
    }

    public function selfDownload(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $santriUser = $this->resolveAuthenticatedSantri();
        if (! $santriUser) {
            return response()->json([
                'message' => 'Akses hanya untuk akun santri.',
            ], 403);
        }

        $payload = $this->buildRaportPayload(
            nomorInduk: (string) $santriUser->nomor_induk,
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $filename = $this->buildFilename(
            nomorInduk: (string) $santriUser->nomor_induk,
            tahunAjaran: $validated['tahun_ajaran'],
            semester: (int) $validated['semester']
        );

        $this->logDownload(
            request: $request,
            raport: $payload['raport'],
            filename: $filename,
            tipePengunduh: 'SANTRI',
            idPetugas: null,
            keterangan: 'Unduh PDF rapor oleh santri (self-service).'
        );

        return Pdf::loadView('pdf.raport', $payload)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return array{raport: DataRaport, santri: DataSantri, kelas: DataKelas|null, unit: mixed, waliKelas: DataPetugas|null, nilaiMapel: \Illuminate\Support\Collection<int, object>, nilaiAkhlak: \Illuminate\Support\Collection<int, object>, nilaiAkhlakRingkas: array<string, mixed>|null, jumlahNilai: float, rataRataNilai: float}
     */
    private function buildRaportPayload(string $nomorInduk, string $tahunAjaran, int $semester): array
    {
        $raport = DataRaport::query()
            ->where('nomor_induk', $nomorInduk)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->firstOrFail();

        $santri = DataSantri::query()
            ->where('nomor_induk', $nomorInduk)
            ->firstOrFail();

        $kelas = DataKelas::query()
            ->with('unit')
            ->where('kode_kelas', $raport->kode_kelas)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderByDesc('id_kelas')
            ->first();

        $waliKelas = $raport->id_wali_kelas
            ? DataPetugas::query()->find($raport->id_wali_kelas)
            : null;

        $nilaiMapel = DB::table('data_nilai_siswa as ns')
            ->leftJoin('data_mata_pelajaran as mp', 'mp.kode_mapel', '=', 'ns.kode_mapel')
            ->where('ns.nomor_induk', $nomorInduk)
            ->where('ns.tahun_ajaran', $tahunAjaran)
            ->where('ns.semester', $semester)
            ->select([
                'ns.kode_mapel',
                'mp.nama_mapel',
                'mp.kelompok_mapel',
                'mp.keterangan as keterangan_mapel',
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
            kodeKelas: (string) $raport->kode_kelas,
            tahunAjaran: $tahunAjaran
        );

        $nilaiMapel = $this->appendKonversiToNilaiMapel($nilaiMapel, $konversiRows);

        $nilaiAkhlak = NilaiAkhlak::query()
            ->where('nomor_induk', $nomorInduk)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderBy('aspek')
            ->get(['aspek', 'nilai_angka', 'deskripsi']);

        $nilaiAkhlakRingkas = $this->buildNilaiAkhlakRingkas($nilaiAkhlak);
        $nilaiKeseluruhan = $this->calculateNilaiKeseluruhan($nilaiMapel, $nilaiAkhlakRingkas);

        return [
            'raport' => $raport,
            'santri' => $santri,
            'kelas' => $kelas,
            'unit' => $kelas?->unit,
            'waliKelas' => $waliKelas,
            'nilaiMapel' => $nilaiMapel,
            'nilaiAkhlak' => $nilaiAkhlak,
            'nilaiAkhlakRingkas' => $nilaiAkhlakRingkas,
            'jumlahNilai' => $nilaiKeseluruhan['jumlah_nilai'],
            'rataRataNilai' => $nilaiKeseluruhan['rata_rata_nilai'],
        ];
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

    private function appendKonversiToNilaiMapel($nilaiMapel, $konversiRows)
    {
        return $nilaiMapel->map(function ($row) use ($konversiRows) {
            $konversi = $this->matchKonversiNilai((float) ($row->nilai_rapor_tampil ?? 0), $konversiRows);

            $row->nilai_huruf = $konversi['nilai_huruf'];
            $row->predikat = $konversi['predikat'];

            return $row;
        });
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

    private function roundHalfUp(float $value, int $precision): float
    {
        $factor = 10 ** $precision;

        return floor(($value * $factor) + 0.5) / $factor;
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $nilaiMapel
     * @param array{angka:?float}|null $nilaiAkhlakRingkas
     * @return array{jumlah_nilai: float, rata_rata_nilai: float, jumlah_komponen: int}
     */
    private function calculateNilaiKeseluruhan($nilaiMapel, ?array $nilaiAkhlakRingkas): array
    {
        $jumlahNilaiMapel = (float) $nilaiMapel->sum(fn($row) => (float) ($row->nilai_rapor_tampil ?? 0));
        $jumlahKomponen = $nilaiMapel->count();
        $jumlahNilaiAkhlak = (float) ($nilaiAkhlakRingkas['angka'] ?? 0);

        if ($nilaiAkhlakRingkas !== null) {
            $jumlahNilaiMapel += $jumlahNilaiAkhlak;
            $jumlahKomponen++;
        }

        $jumlahNilai = $this->roundHalfUp($jumlahNilaiMapel, 2);
        $rataRataNilai = $jumlahKomponen > 0
            ? $this->roundHalfUp($jumlahNilai / $jumlahKomponen, 2)
            : 0.0;

        return [
            'jumlah_nilai' => $jumlahNilai,
            'rata_rata_nilai' => $rataRataNilai,
            'jumlah_komponen' => $jumlahKomponen,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $nilaiAkhlak
     * @return array{label:string, angka:?float, huruf:?string, keterangan:?string, detail:string}|null
     */
    private function buildNilaiAkhlakRingkas($nilaiAkhlak): ?array
    {
        if ($nilaiAkhlak->isEmpty()) {
            return null;
        }

        $angkaRataRata = $this->roundHalfUp((float) $nilaiAkhlak->avg('nilai_angka'), 2);

        $detail = $nilaiAkhlak->map(function ($row) {
            return trim((string) ($row->deskripsi ?? ''));
        })->filter()->implode('; ');

        if ($detail === '') {
            $detail = '-';
        }

        return [
            'label' => 'Akhlaq',
            'angka' => $angkaRataRata,
            'huruf' => '',
            'keterangan' => $detail,
            'detail' => $detail,
        ];
    }

    private function buildFilename(string $nomorInduk, string $tahunAjaran, int $semester): string
    {
        $safeTahun = str_replace(['/', '\\', ' '], ['-', '-', '_'], $tahunAjaran);

        return 'raport-' . $nomorInduk . '-' . $safeTahun . '-s' . $semester . '.pdf';
    }

    private function resolveAuthenticatedSantri(): ?DataAkunSantri
    {
        $user = Auth::guard('santri')->user();

        if ($user instanceof DataAkunSantri) {
            return $user;
        }

        $user = Auth::user();

        return $user instanceof DataAkunSantri ? $user : null;
    }

    private function resolvePetugasId(): ?int
    {
        $user = Auth::guard('petugas')->user();

        if ($user instanceof DataPetugas) {
            return (int) $user->id_petugas;
        }

        $user = Auth::user();

        return $user instanceof DataPetugas ? (int) $user->id_petugas : null;
    }

    private function logDownload(Request $request, DataRaport $raport, string $filename, string $tipePengunduh, ?int $idPetugas, string $keterangan): void
    {
        LogDownloadRaport::create([
            'id_raport' => $raport->id_raport,
            'nomor_induk' => $raport->nomor_induk,
            'id_petugas' => $idPetugas,
            'tipe_pengunduh' => $tipePengunduh,
            'aksi' => 'DOWNLOAD',
            'nama_file_pdf' => $filename,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'status_aksi' => 'SUKSES',
            'keterangan' => $keterangan,
        ]);
    }
}
