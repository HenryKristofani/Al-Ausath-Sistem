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

        $query = NilaiAkhlak::query()
            ->with(['santri', 'petugas'])
            ->when($request->filled('nomor_induk'), fn ($q) => $q->where('nomor_induk', $request->nomor_induk))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $request->semester))
            ->when($request->filled('aspek'), fn ($q) => $q->where('aspek', $request->aspek))
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
}
