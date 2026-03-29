<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\BobotNilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BobotNilaiController extends Controller
{
    /**
     * List bobot nilai global.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = BobotNilai::query()
            ->global()
            ->when($request->filled('tahun_ajaran'), fn($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('semester'), fn($q) => $q->where('semester', (int) $request->semester))
            ->orderByDesc('id_bobot');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan bobot nilai global.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'bobot_harian' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_uts' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_uas' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $totalBobot = (float) $validated['bobot_harian'] + (float) $validated['bobot_uts'] + (float) $validated['bobot_uas'];
        if (round($totalBobot, 2) !== 100.0) {
            return response()->json([
                'message' => 'Total bobot nilai harus 100.',
            ], 422);
        }

        try {
            $data = BobotNilai::create($validated);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Bobot nilai berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Detail bobot nilai.
     */
    public function show(int $id): JsonResponse
    {
        $data = BobotNilai::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui bobot nilai.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bobot = BobotNilai::findOrFail($id);

        $validated = $request->validate([
            'tahun_ajaran' => ['sometimes', 'string', 'max:20'],
            'semester' => ['sometimes', 'integer', 'in:1,2'],
            'bobot_harian' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'bobot_uts' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'bobot_uas' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $merged = [
            'bobot_harian' => array_key_exists('bobot_harian', $validated) ? $validated['bobot_harian'] : $bobot->bobot_harian,
            'bobot_uts' => array_key_exists('bobot_uts', $validated) ? $validated['bobot_uts'] : $bobot->bobot_uts,
            'bobot_uas' => array_key_exists('bobot_uas', $validated) ? $validated['bobot_uas'] : $bobot->bobot_uas,
        ];

        $totalBobot = (float) $merged['bobot_harian'] + (float) $merged['bobot_uts'] + (float) $merged['bobot_uas'];
        if (round($totalBobot, 2) !== 100.0) {
            return response()->json([
                'message' => 'Total bobot nilai harus 100.',
            ], 422);
        }

        try {
            $bobot->update($validated);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Bobot nilai berhasil diperbarui.',
            'data' => $bobot->fresh(),
        ]);
    }

    /**
     * Hapus bobot nilai.
     */
    public function destroy(int $id): JsonResponse
    {
        $bobot = BobotNilai::findOrFail($id);
        $bobot->delete();

        return response()->json([
            'message' => 'Bobot nilai berhasil dihapus.',
        ]);
    }

    /**
     * Set bobot default client 20/30/50.
     */
    public function setDefault(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $data = BobotNilai::updateOrCreate(
            [
                'jenjang' => BobotNilai::GLOBAL_JENJANG,
                'kode_unit' => null,
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'semester' => $validated['semester'],
            ],
            [
                'bobot_harian' => 20,
                'bobot_uts' => 30,
                'bobot_uas' => 50,
            ]
        );

        return response()->json([
            'message' => 'Bobot default 20/30/50 berhasil diset.',
            'data' => $data,
        ]);
    }
}
