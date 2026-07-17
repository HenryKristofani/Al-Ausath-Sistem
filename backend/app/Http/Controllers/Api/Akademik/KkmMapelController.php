<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataPetugas;
use App\Models\KkmMapel;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KkmMapelController extends Controller
{
    private const MAPEL_ROLES = [
        'guru_mapel',
        'guru mapel',
        'mapel',
        'staf pengajar',
    ];

    private const ADMIN_ROLES = [
        'petugas admin',
        'admin',
        'administrator',
    ];

    /**
     * List KKM mapel.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $kodeUnit = $request->query('kode_unit');

        $query = KkmMapel::query()
            ->with(['mataPelajaran', 'unit'])
            ->when($request->filled('kode_mapel'), fn($q) => $q->where('kode_mapel', $request->kode_mapel))
            ->when($request->filled('tahun_ajaran'), fn($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('semester'), fn($q) => $q->where('semester', (int) $request->semester));

        if ($request->filled('kode_unit')) {
            $query->where(function ($q) use ($kodeUnit) {
                $q->where('kode_unit', $kodeUnit)
                    ->orWhereNull('kode_unit');
            });

            // Prioritaskan data spesifik unit, lalu fallback data global.
            $query->orderByRaw('CASE WHEN kode_unit IS NULL THEN 1 ELSE 0 END');
        }

        $query->orderByDesc('id_kkm');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan KKM mapel.
     */
    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeKkmMutation(canOverride: false)) {
            return $response;
        }

        $validated = $request->validate([
            'kode_mapel' => ['required', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'nilai_kkm' => ['required', 'numeric', 'min:0', 'max:100'],
            'status_ketuntasan' => ['nullable', 'string', 'in:menguasai,ahli,menerapkan'],
            'keterangan' => ['nullable', 'string'],
        ]);

        try {
            $data = KkmMapel::create($validated);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                return response()->json([
                    'message' => 'KKM untuk kombinasi mapel/unit/tahun ajaran/semester ini sudah ada.',
                ], 422);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'KKM mapel berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Detail KKM mapel.
     */
    public function show(int $id): JsonResponse
    {
        $data = KkmMapel::with(['mataPelajaran', 'unit'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui KKM mapel.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeKkmMutation(canOverride: true, allowMapelOverride: true)) {
            return $response;
        }

        $kkm = KkmMapel::findOrFail($id);

        $validated = $request->validate([
            'kode_mapel' => ['sometimes', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'tahun_ajaran' => ['sometimes', 'string', 'max:20'],
            'semester' => ['sometimes', 'integer', 'in:1,2'],
            'nilai_kkm' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status_ketuntasan' => ['nullable', 'string', 'in:menguasai,ahli,menerapkan'],
            'keterangan' => ['nullable', 'string'],
        ]);

        try {
            $kkm->update($validated);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                return response()->json([
                    'message' => 'KKM untuk kombinasi mapel/unit/tahun ajaran/semester ini sudah ada.',
                ], 422);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'KKM mapel berhasil diperbarui.',
            'data' => $kkm->fresh(['mataPelajaran', 'unit']),
        ]);
    }

    /**
     * Hapus KKM mapel.
     */
    public function destroy(int $id): JsonResponse
    {
        if ($response = $this->authorizeKkmMutation(canOverride: true, allowMapelOverride: true)) {
            return $response;
        }

        $kkm = KkmMapel::findOrFail($id);
        $kkm->delete();

        return response()->json([
            'message' => 'KKM mapel berhasil dihapus.',
        ]);
    }

    private function authorizeKkmMutation(bool $canOverride, bool $allowMapelOverride = false): ?JsonResponse
    {
        $petugas = $this->resolvePetugasAuth();

        if (! $petugas) {
            return response()->json([
                'message' => 'Hanya petugas yang dapat mengelola KKM mapel.',
            ], 403);
        }

        $rawRoles = $petugas->peran_akun;
        $roles = is_array($rawRoles) ? $rawRoles : [$rawRoles];
        $roles = array_filter(array_map(fn ($role) => strtolower(trim((string) $role)), $roles), fn ($role) => $role !== '');

        if (count(array_intersect($roles, self::ADMIN_ROLES)) > 0) {
            if ($canOverride) {
                return null;
            }

            return response()->json([
                'message' => 'Admin hanya diizinkan melakukan override terkontrol (update/hapus).',
            ], 403);
        }

        if (count(array_intersect($roles, self::MAPEL_ROLES)) > 0) {
            if (! $canOverride || $allowMapelOverride) {
                return null;
            }

            return response()->json([
                'message' => 'Hapus KKM hanya diizinkan untuk admin.',
            ], 403);
        }

        return response()->json([
            'message' => 'Akun petugas ini tidak memiliki hak kelola KKM mapel.',
        ], 403);
    }

    private function resolvePetugasAuth(): ?DataPetugas
    {
        $user = Auth::guard('petugas')->user();

        if ($user instanceof DataPetugas) {
            return $user;
        }

        $user = Auth::user();

        return $user instanceof DataPetugas ? $user : null;
    }
}
