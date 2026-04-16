<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataRaport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RangkingKelasController extends Controller
{
    /**
     * Generate ulang ranking kelas berdasarkan data raport semester berjalan.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $raportRows = DataRaport::query()
            ->leftJoin('data_santri as santri', 'santri.nomor_induk', '=', 'data_raport.nomor_induk')
            ->where('data_raport.kode_kelas', $validated['kode_kelas'])
            ->where('data_raport.tahun_ajaran', $validated['tahun_ajaran'])
            ->where('data_raport.semester', (int) $validated['semester'])
            ->select([
                'data_raport.id_raport',
                'data_raport.nomor_induk',
                'data_raport.rata_rata',
                'data_raport.jumlah_nilai',
                'santri.nama_lengkap_santri',
            ])
            ->get();

        if ($raportRows->isEmpty()) {
            return response()->json([
                'message' => 'Data raport untuk kelas dan semester ini belum tersedia.',
            ], 422);
        }

        $sorted = $this->sortRankingRows($raportRows);
        $total = $sorted->count();

        DB::transaction(function () use ($sorted, $total): void {
            foreach ($sorted as $index => $row) {
                DataRaport::query()
                    ->where('id_raport', (int) $row->id_raport)
                    ->update([
                        'peringkat_kelas' => $index + 1,
                        'total_siswa_kelas' => $total,
                    ]);
            }
        });

        return response()->json([
            'message' => 'Ranking kelas berhasil digenerate ulang dari data raport terbaru.',
            'data' => [
                'kode_kelas' => $validated['kode_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => (int) $validated['semester'],
                'total_siswa' => $total,
                'generated_at' => now()->toDateTimeString(),
                'ranking' => $sorted->values()->map(function ($row, $index) use ($total) {
                    return [
                        'peringkat_kelas' => $index + 1,
                        'total_siswa_kelas' => $total,
                        'nomor_induk' => $row->nomor_induk,
                        'nama_lengkap_santri' => $row->nama_lengkap_santri,
                        'rata_rata' => (float) $row->rata_rata,
                        'jumlah_nilai' => (float) $row->jumlah_nilai,
                    ];
                }),
            ],
        ]);
    }

    private function sortRankingRows(Collection $rows): Collection
    {
        return $rows
            ->sort(function ($a, $b): int {
                $compareRataRata = ((float) $b->rata_rata) <=> ((float) $a->rata_rata);
                if ($compareRataRata !== 0) {
                    return $compareRataRata;
                }

                $compareJumlahNilai = ((float) $b->jumlah_nilai) <=> ((float) $a->jumlah_nilai);
                if ($compareJumlahNilai !== 0) {
                    return $compareJumlahNilai;
                }

                $namaA = mb_strtolower((string) ($a->nama_lengkap_santri ?? ''));
                $namaB = mb_strtolower((string) ($b->nama_lengkap_santri ?? ''));
                $compareNama = $namaA <=> $namaB;
                if ($compareNama !== 0) {
                    return $compareNama;
                }

                return ((string) $a->nomor_induk) <=> ((string) $b->nomor_induk);
            })
            ->values();
    }
}