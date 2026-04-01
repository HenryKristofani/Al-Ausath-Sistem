<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
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
     * @return array{raport: DataRaport, santri: DataSantri, nilaiMapel: \Illuminate\Support\Collection<int, object>, nilaiAkhlak: \Illuminate\Support\Collection<int, object>}
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

        $nilaiAkhlak = NilaiAkhlak::query()
            ->where('nomor_induk', $nomorInduk)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->orderBy('aspek')
            ->get(['aspek', 'nilai_angka', 'deskripsi']);

        return [
            'raport' => $raport,
            'santri' => $santri,
            'nilaiMapel' => $nilaiMapel,
            'nilaiAkhlak' => $nilaiAkhlak,
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
