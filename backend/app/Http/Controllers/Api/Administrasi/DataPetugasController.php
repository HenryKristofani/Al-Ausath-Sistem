<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataPetugas;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DataPetugasController extends Controller
{
    /**
     * List data petugas.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataPetugas::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('peran_akun'), fn ($q) => $q->where('peran_akun', $request->peran_akun))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('alamat_email', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_petugas');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data petugas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['nullable', 'string', 'max:20', 'unique:data_petugas,nomor_induk'],
            'nama_lengkap' => ['required', 'string', 'max:200'],
            'peran_akun' => ['required', 'string', Rule::in(DataPetugas::PERAN_AKUN_OPTIONS)],
            'pilihan_unit' => ['nullable', 'string', 'max:10'],
            'alamat_email' => ['required', 'email', 'max:100', 'unique:data_petugas,alamat_email'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $data = DataPetugas::create([
            'nomor_induk' => $validated['nomor_induk'] ?? null,
            'nama_lengkap' => $validated['nama_lengkap'],
            'peran_akun' => $validated['peran_akun'],
            'pilihan_unit' => $validated['pilihan_unit'] ?? null,
            'alamat_email' => $validated['alamat_email'],
            'nomor_telepon' => $validated['nomor_telepon'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'status' => strtoupper($validated['status'] ?? 'AKTIF'),
        ]);

        return response()->json([
            'message' => 'Data petugas berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data petugas.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataPetugas::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data petugas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $petugas = DataPetugas::findOrFail($id);

        $validated = $request->validate([
            'nomor_induk' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('data_petugas', 'nomor_induk')->ignore($petugas->id_petugas, 'id_petugas'),
            ],
            'nama_lengkap' => ['sometimes', 'string', 'max:200'],
            'peran_akun' => ['sometimes', 'string', Rule::in(DataPetugas::PERAN_AKUN_OPTIONS)],
            'pilihan_unit' => ['nullable', 'string', 'max:10'],
            'alamat_email' => [
                'sometimes',
                'email',
                'max:100',
                Rule::unique('data_petugas', 'alamat_email')->ignore($petugas->id_petugas, 'id_petugas'),
            ],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        if (!empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
        }

        unset($validated['password']);

        $petugas->update($validated);

        return response()->json([
            'message' => 'Data petugas berhasil diperbarui.',
            'data' => $petugas->fresh(),
        ]);
    }

    /**
     * Hapus data petugas.
     */
    public function destroy(int $id): JsonResponse
    {
        $petugas = DataPetugas::findOrFail($id);

        try {
            $petugas->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data petugas tidak dapat dihapus karena masih dipakai pada data lain.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data petugas berhasil dihapus.',
        ]);
    }

    /**
     * Ambil opsi peran akun untuk form master data petugas.
     */
    public function peranAkunOptions(): JsonResponse
    {
        return response()->json([
            'data' => DataPetugas::PERAN_AKUN_OPTIONS,
        ]);
    }
}
