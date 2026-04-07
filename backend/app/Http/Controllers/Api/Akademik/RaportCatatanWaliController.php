<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataRaport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RaportCatatanWaliController extends Controller
{
    /**
     * Ambil catatan wali per santri-semester.
     */
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
            ->where('semester', $validated['semester'])
            ->first();

        return response()->json([
            'data' => [
                'nomor_induk' => $validated['nomor_induk'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => (int) $validated['semester'],
                'catatan_wali' => $raport?->catatan_wali,
                'id_wali_kelas' => $raport?->id_wali_kelas,
                'keseharian_kebersihan' => $raport?->keseharian_kebersihan,
                'keseharian_kerapian' => $raport?->keseharian_kerapian,
                'keseharian_keterampilan' => $raport?->keseharian_keterampilan,
            ],
        ]);
    }

    /**
     * Simpan catatan pengembangan diri dari wali kelas.
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'catatan_wali' => ['required', 'string'],
            'id_wali_kelas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'keseharian_kebersihan' => ['nullable', 'string', 'max:1'],
            'keseharian_kerapian' => ['nullable', 'string', 'max:1'],
            'keseharian_keterampilan' => ['nullable', 'string', 'max:1'],
        ]);

        $raport = DataRaport::updateOrCreate(
            [
                'nomor_induk' => $validated['nomor_induk'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            [
                'kode_kelas' => $validated['kode_kelas'],
                'catatan_wali' => $validated['catatan_wali'],
                'id_wali_kelas' => $validated['id_wali_kelas'] ?? null,
                'keseharian_kebersihan' => $validated['keseharian_kebersihan'] ?? null,
                'keseharian_kerapian' => $validated['keseharian_kerapian'] ?? null,
                'keseharian_keterampilan' => $validated['keseharian_keterampilan'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Catatan wali kelas berhasil disimpan.',
            'data' => $raport,
        ]);
    }
}
