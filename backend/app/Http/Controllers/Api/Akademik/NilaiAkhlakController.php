<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\NilaiAkhlak;
use App\Models\DataSantri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NilaiAkhlakController extends Controller
{
    /**
     * List nilai akhlak dengan filter standar raport.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'aspek' => ['nullable', 'string', 'max:80'],
        ]);

        $query = NilaiAkhlak::query()
            ->with(['santri', 'petugas'])
            ->where('nomor_induk', $validated['nomor_induk'])
            ->when(array_key_exists('tahun_ajaran', $validated), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(array_key_exists('semester', $validated), fn($q) => $q->where('semester', $validated['semester']))
            ->when(array_key_exists('aspek', $validated), fn($q) => $q->where('aspek', $validated['aspek']))
            ->orderByDesc('id_akhlak');

        return response()->json($query->paginate($perPage));
    }

    /**
     * List semua nilai akhlak tanpa filter nomor induk (untuk dashboard/laporan).
     */
    public function bar(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $validated = $request->validate([
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'aspek' => ['nullable', 'string', 'max:80'],
        ]);

        $query = NilaiAkhlak::query()
            ->with(['santri', 'petugas'])
            ->when(array_key_exists('tahun_ajaran', $validated), fn($q) => $q->where('tahun_ajaran', $validated['tahun_ajaran']))
            ->when(array_key_exists('semester', $validated), fn($q) => $q->where('semester', $validated['semester']))
            ->when(array_key_exists('aspek', $validated), fn($q) => $q->where('aspek', $validated['aspek']))
            ->orderByDesc('id_akhlak');

        return response()->json($query->paginate($perPage));
    }

    /**
     * List nilai akhlak untuk seluruh santri dalam satu kelas.
     */
    public function kelasIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'aspek' => ['nullable', 'string', 'max:80'],
        ]);

        $aspek = $validated['aspek'] ?? 'AKHLAK';

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

        $nilais = NilaiAkhlak::query()
            ->where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', (int) $validated['semester'])
            ->where('aspek', $aspek)
            ->whereIn('nomor_induk', $santris->pluck('nomor_induk'))
            ->get()
            ->keyBy('nomor_induk');

        $result = $santris->map(function ($santri) use ($nilais) {
            $nilai = $nilais->get($santri->nomor_induk);
            
            return [
                'id' => $santri->id_santri,
                'nomor_induk' => $santri->nomor_induk,
                'nama_santri' => $santri->nama_lengkap_santri,
                'nilai_akhlak' => $nilai ? $nilai->toArray() : null,
            ];
        });

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Simpan atau update nilai akhlak berbasis angka.
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'aspek' => ['nullable', 'string', 'max:80'],
            'nilai_angka' => ['required', 'numeric', 'min:0', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'id_petugas_input' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
        ]);

        $aspek = $validated['aspek'] ?? 'AKHLAK';

        $nilai = NilaiAkhlak::updateOrCreate(
            [
                'nomor_induk' => $validated['nomor_induk'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
                'aspek' => $aspek,
            ],
            [
                'nilai_angka' => $validated['nilai_angka'],
                // Backward compatibility untuk skema lama yang masih memiliki kolom predikat.
                'predikat' => '-',
                'deskripsi' => $validated['deskripsi'] ?? null,
                'id_petugas_input' => $validated['id_petugas_input'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Nilai akhlak berhasil disimpan.',
            'data' => $nilai->fresh(['santri', 'petugas']),
        ]);
    }

    /**
     * Hapus nilai akhlak.
     */
    public function destroy(int $id): JsonResponse
    {
        $nilai = NilaiAkhlak::findOrFail($id);
        $nilai->delete();

        return response()->json([
            'message' => 'Nilai akhlak berhasil dihapus.',
        ]);
    }
}
