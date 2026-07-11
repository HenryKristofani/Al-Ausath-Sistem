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
                'keseharian_kelakuan' => $raport?->keseharian_kelakuan,
                'keseharian_kerajinan' => $raport?->keseharian_kerajinan,
                'keseharian_kedisiplinan' => $raport?->keseharian_kedisiplinan,
                'keseharian_ketaatan' => $raport?->keseharian_ketaatan,
                'ekstrakurikuler' => $raport?->ekstrakurikuler,
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
            'keseharian_kelakuan' => ['nullable', 'string', 'max:1'],
            'keseharian_kerajinan' => ['nullable', 'string', 'max:1'],
            'keseharian_kedisiplinan' => ['nullable', 'string', 'max:1'],
            'keseharian_ketaatan' => ['nullable', 'string', 'max:1'],
            'ekstrakurikuler' => ['nullable', 'array'],
            'ekstrakurikuler.*.nama' => ['required_with:ekstrakurikuler', 'string', 'max:100'],
            'ekstrakurikuler.*.nilai' => ['required_with:ekstrakurikuler', 'string', 'max:10'],
        ]);

        $raport = DataRaport::query()->firstOrCreate(
            [
                'nomor_induk' => $validated['nomor_induk'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            ['kode_kelas' => $validated['kode_kelas']]
        );

        $updatePayload = [
            'kode_kelas'  => $validated['kode_kelas'],
            'catatan_wali' => $validated['catatan_wali'],
            'id_wali_kelas' => $validated['id_wali_kelas'] ?? null,
        ];

        $keseharianFields = [
            'keseharian_kebersihan',
            'keseharian_kerapian',
            'keseharian_keterampilan',
            'keseharian_kelakuan',
            'keseharian_kerajinan',
            'keseharian_kedisiplinan',
            'keseharian_ketaatan',
        ];

        foreach ($keseharianFields as $field) {
            if ($request->has($field)) {
                $updatePayload[$field] = $validated[$field] ?? null;
            }
        }

        if ($request->has('ekstrakurikuler')) {
            $updatePayload['ekstrakurikuler'] = $validated['ekstrakurikuler'] ?? null;
        }

        $raport->update($updatePayload);

        return response()->json([
            'message' => 'Catatan wali kelas berhasil disimpan.',
            'data' => $raport->fresh(),
        ]);
    }
    /**
     * Simpan catatan wali massal.
     */
    public function bulkUpsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'santris' => ['required', 'array'],
            'santris.*.nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk'],
            'santris.*.catatan_wali' => ['nullable', 'string'],
            'santris.*.id_wali_kelas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'santris.*.keseharian_kebersihan' => ['nullable', 'string', 'max:1'],
            'santris.*.keseharian_kerapian' => ['nullable', 'string', 'max:1'],
            'santris.*.keseharian_keterampilan' => ['nullable', 'string', 'max:1'],
            'santris.*.keseharian_kelakuan' => ['nullable', 'string', 'max:1'],
            'santris.*.keseharian_kerajinan' => ['nullable', 'string', 'max:1'],
            'santris.*.keseharian_kedisiplinan' => ['nullable', 'string', 'max:1'],
            'santris.*.keseharian_ketaatan' => ['nullable', 'string', 'max:1'],
            'santris.*.ekstrakurikuler' => ['nullable', 'array'],
            'santris.*.ekstrakurikuler.*.nama' => ['required_with:santris.*.ekstrakurikuler', 'string', 'max:100'],
            'santris.*.ekstrakurikuler.*.nilai' => ['required_with:santris.*.ekstrakurikuler', 'string', 'max:10'],
        ]);

        $keseharianFields = [
            'keseharian_kebersihan',
            'keseharian_kerapian',
            'keseharian_keterampilan',
            'keseharian_kelakuan',
            'keseharian_kerajinan',
            'keseharian_kedisiplinan',
            'keseharian_ketaatan',
        ];

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $keseharianFields) {
            foreach ($validated['santris'] as $santriData) {
                $raport = DataRaport::query()->firstOrCreate(
                    [
                        'nomor_induk' => $santriData['nomor_induk'],
                        'tahun_ajaran' => $validated['tahun_ajaran'],
                        'semester' => $validated['semester'],
                    ],
                    ['kode_kelas' => $validated['kode_kelas']]
                );

                $updatePayload = [
                    'kode_kelas'  => $validated['kode_kelas'],
                ];

                if (array_key_exists('catatan_wali', $santriData)) {
                    $updatePayload['catatan_wali'] = $santriData['catatan_wali'];
                }

                if (array_key_exists('id_wali_kelas', $santriData)) {
                    $updatePayload['id_wali_kelas'] = $santriData['id_wali_kelas'];
                }

                foreach ($keseharianFields as $field) {
                    if (array_key_exists($field, $santriData)) {
                        $updatePayload[$field] = $santriData[$field];
                    }
                }

                if (array_key_exists('ekstrakurikuler', $santriData)) {
                    $updatePayload['ekstrakurikuler'] = $santriData['ekstrakurikuler'];
                }

                $raport->update($updatePayload);
            }
        });

        return response()->json([
            'message' => 'Catatan wali massal berhasil disimpan.',
        ]);
    }
}
