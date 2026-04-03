<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\NilaiAkhlak;
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
