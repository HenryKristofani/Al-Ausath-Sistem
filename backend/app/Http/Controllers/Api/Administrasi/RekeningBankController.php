<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataRekeningBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RekeningBankController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List semua rekening bank. Public-accessible setelah login,
     * agar halaman pembayaran (santri, PPDB, dll) bisa menampilkannya.
     */
    public function index(Request $request): JsonResponse
    {
        $rows = DataRekeningBank::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->orderBy('nama_bank')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Simpan rekening baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_rekening' => ['required', 'string', 'max:200'],
            'nama_pemilik'  => ['required', 'string', 'max:200'],
            'nomor_rekening' => ['required', 'string', 'max:50', 'unique:data_rekening_bank,nomor_rekening'],
            'nama_bank'     => ['required', 'string', 'max:100'],
            'cabang_bank'   => ['nullable', 'string', 'max:200'],
            'peruntukan'    => ['nullable', 'string'],
            'status'        => ['nullable', 'in:AKTIF,NONAKTIF'],
        ]);

        $validated['status'] = $validated['status'] ?? 'AKTIF';

        $rekening = DataRekeningBank::create($validated);

        return response()->json([
            'message' => 'Rekening bank berhasil ditambahkan.',
            'data'    => $rekening,
        ], 201);
    }

    /**
     * Detail rekening bank.
     */
    public function show(int $id): JsonResponse
    {
        $rekening = DataRekeningBank::findOrFail($id);
        return response()->json(['data' => $rekening]);
    }

    /**
     * Perbarui rekening bank.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rekening = DataRekeningBank::findOrFail($id);

        $validated = $request->validate([
            'nama_rekening' => ['sometimes', 'string', 'max:200'],
            'nama_pemilik'  => ['sometimes', 'string', 'max:200'],
            'nomor_rekening' => ['sometimes', 'string', 'max:50', 'unique:data_rekening_bank,nomor_rekening,' . $id . ',id_rekening'],
            'nama_bank'     => ['sometimes', 'string', 'max:100'],
            'cabang_bank'   => ['nullable', 'string', 'max:200'],
            'peruntukan'    => ['nullable', 'string'],
            'status'        => ['nullable', 'in:AKTIF,NONAKTIF'],
        ]);

        $rekening->update($validated);

        return response()->json([
            'message' => 'Rekening bank berhasil diperbarui.',
            'data'    => $rekening->fresh(),
        ]);
    }

    /**
     * Hapus rekening bank.
     */
    public function destroy(int $id): JsonResponse
    {
        $rekening = DataRekeningBank::findOrFail($id);
        $rekening->delete();

        return response()->json(['message' => 'Rekening bank berhasil dihapus.']);
    }
}
