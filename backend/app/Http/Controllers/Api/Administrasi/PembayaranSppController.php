<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\PembayaranSpp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranSppController extends Controller
{
    /**
     * List pembayaran SPP.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = PembayaranSpp::query()
            ->with(['santri', 'setting', 'rekening'])
            ->when($request->filled('id_santri'), fn ($q) => $q->where('id_santri', $request->id_santri))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('tanggal_mulai'), fn ($q) => $q->whereDate('tanggal_bayar', '>=', $request->tanggal_mulai))
            ->when($request->filled('tanggal_selesai'), fn ($q) => $q->whereDate('tanggal_bayar', '<=', $request->tanggal_selesai))
            ->orderByDesc('id_pembayaran');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan pembayaran SPP.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_santri' => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'id_setting' => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'nominal_bayar' => ['nullable', 'numeric'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $data = PembayaranSpp::create($validated);

        return response()->json([
            'message' => 'Pembayaran SPP berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Detail pembayaran SPP.
     */
    public function show(int $id): JsonResponse
    {
        $data = PembayaranSpp::with(['santri', 'setting', 'rekening'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui pembayaran SPP.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);

        $validated = $request->validate([
            'id_santri' => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'id_setting' => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'nominal_bayar' => ['nullable', 'numeric'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $pembayaran->update($validated);

        return response()->json([
            'message' => 'Pembayaran SPP berhasil diperbarui.',
            'data' => $pembayaran->fresh(['santri', 'setting', 'rekening']),
        ]);
    }

    /**
     * Hapus pembayaran SPP.
     */
    public function destroy(int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);
        $pembayaran->delete();

        return response()->json([
            'message' => 'Pembayaran SPP berhasil dihapus.',
        ]);
    }
}
