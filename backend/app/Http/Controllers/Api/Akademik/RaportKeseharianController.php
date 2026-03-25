<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataRaport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RaportKeseharianController extends Controller
{
    /**
     * Ambil data keseharian raport per santri-semester.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20'],
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
                'kebersihan' => $raport?->keseharian_kebersihan,
                'kerapian' => $raport?->keseharian_kerapian,
                'keterampilan' => $raport?->keseharian_keterampilan,
            ],
        ]);
    }

    /**
     * Simpan komponen keseharian raport (A/B/C/D).
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'kebersihan' => ['required', 'string', 'size:1', 'in:A,B,C,D'],
            'kerapian' => ['required', 'string', 'size:1', 'in:A,B,C,D'],
            'keterampilan' => ['required', 'string', 'size:1', 'in:A,B,C,D'],
            'id_wali_kelas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
        ]);

        $raport = DataRaport::updateOrCreate(
            [
                'nomor_induk' => $validated['nomor_induk'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            [
                'kode_kelas' => $validated['kode_kelas'],
                'keseharian_kebersihan' => strtoupper($validated['kebersihan']),
                'keseharian_kerapian' => strtoupper($validated['kerapian']),
                'keseharian_keterampilan' => strtoupper($validated['keterampilan']),
                'id_wali_kelas' => $validated['id_wali_kelas'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Keseharian raport berhasil disimpan.',
            'data' => $raport,
        ]);
    }
}
