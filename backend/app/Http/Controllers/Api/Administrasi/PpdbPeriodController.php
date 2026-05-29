<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\PpdbPeriod;
use App\Models\PpdbPendaftar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PpdbPeriodController extends Controller
{
    /**
     * Daftar semua gelombang PPDB.
     */
    public function index(Request $request)
    {
        $query = PpdbPeriod::query()->orderByDesc('created_at');

        if ($request->filled('tahun_ajaran')) {
            $query->where('tahun_ajaran', $request->tahun_ajaran);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $periods = $query->get()->map(function ($period) {
            return array_merge($period->toArray(), [
                'jumlah_pendaftar' => $period->jumlahPendaftar(),
                'is_buka' => $period->isBuka(),
                'is_kuota_penuh' => $period->isKuotaPenuh(),
            ]);
        });

        return response()->json([
            'message' => 'Daftar gelombang PPDB berhasil dimuat.',
            'data' => $periods,
        ]);
    }

    /**
     * Detail satu gelombang.
     */
    public function show($id)
    {
        $period = PpdbPeriod::findOrFail($id);

        return response()->json([
            'message' => 'Detail gelombang PPDB berhasil dimuat.',
            'data' => array_merge($period->toArray(), [
                'jumlah_pendaftar' => $period->jumlahPendaftar(),
                'is_buka' => $period->isBuka(),
                'is_kuota_penuh' => $period->isKuotaPenuh(),
            ]),
        ]);
    }

    /**
     * Buat gelombang baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_gelombang' => 'required|string|max:100',
            'tahun_ajaran' => 'required|string|max:20',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'kuota' => 'nullable|integer|min:1',
            'biaya_pendaftaran' => 'nullable|numeric|min:0',
            'status' => ['nullable', Rule::in(['draft', 'aktif', 'ditutup', 'selesai'])],
            'deskripsi' => 'nullable|string',
        ]);

        // Jika status baru = aktif, pastikan tidak ada gelombang lain yg aktif
        if (($validated['status'] ?? 'draft') === 'aktif') {
            $existingAktif = PpdbPeriod::where('status', 'aktif')->first();
            if ($existingAktif) {
                return response()->json([
                    'message' => 'Sudah ada gelombang aktif: "' . $existingAktif->nama_gelombang . '". Tutup atau selesaikan terlebih dahulu sebelum mengaktifkan gelombang baru.',
                ], 422);
            }
        }

        $period = PpdbPeriod::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'draft',
            'biaya_pendaftaran' => $validated['biaya_pendaftaran'] ?? 100000,
            'created_by' => Auth::guard('petugas')->id(),
        ]));

        return response()->json([
            'message' => 'Gelombang PPDB berhasil dibuat.',
            'data' => $period,
        ], 201);
    }

    /**
     * Update gelombang.
     */
    public function update(Request $request, $id)
    {
        $period = PpdbPeriod::findOrFail($id);

        $validated = $request->validate([
            'nama_gelombang' => 'sometimes|string|max:100',
            'tahun_ajaran' => 'sometimes|string|max:20',
            'tanggal_mulai' => 'sometimes|date',
            'tanggal_selesai' => 'sometimes|date|after_or_equal:tanggal_mulai',
            'kuota' => 'nullable|integer|min:1',
            'biaya_pendaftaran' => 'nullable|numeric|min:0',
            'status' => ['sometimes', Rule::in(['draft', 'aktif', 'ditutup', 'selesai'])],
            'deskripsi' => 'nullable|string',
        ]);

        // Jika status diubah ke aktif, pastikan tidak ada gelombang lain yg aktif
        if (isset($validated['status']) && $validated['status'] === 'aktif' && $period->status !== 'aktif') {
            $existingAktif = PpdbPeriod::where('status', 'aktif')
                ->where('id', '!=', $period->id)
                ->first();
            if ($existingAktif) {
                return response()->json([
                    'message' => 'Sudah ada gelombang aktif: "' . $existingAktif->nama_gelombang . '". Tutup atau selesaikan terlebih dahulu.',
                ], 422);
            }
        }

        $period->update($validated);

        return response()->json([
            'message' => 'Gelombang PPDB berhasil diperbarui.',
            'data' => array_merge($period->fresh()->toArray(), [
                'jumlah_pendaftar' => $period->jumlahPendaftar(),
                'is_buka' => $period->isBuka(),
                'is_kuota_penuh' => $period->isKuotaPenuh(),
            ]),
        ]);
    }

    /**
     * Hapus gelombang (hanya jika draft dan tidak ada pendaftar).
     */
    public function destroy($id)
    {
        $period = PpdbPeriod::findOrFail($id);

        if ($period->jumlahPendaftar() > 0) {
            return response()->json([
                'message' => 'Gelombang tidak dapat dihapus karena sudah memiliki ' . $period->jumlahPendaftar() . ' pendaftar.',
            ], 422);
        }

        if (!in_array($period->status, ['draft', 'ditutup'])) {
            return response()->json([
                'message' => 'Hanya gelombang dengan status draft atau ditutup yang dapat dihapus.',
            ], 422);
        }

        $period->delete();

        return response()->json([
            'message' => 'Gelombang PPDB berhasil dihapus.',
        ]);
    }

    /**
     * Endpoint publik: cek apakah PPDB sedang dibuka.
     */
    public function checkPpdbOpen()
    {
        $activePeriod = PpdbPeriod::sedangBerlangsung()->first();

        if (!$activePeriod) {
            return response()->json([
                'message' => 'PPDB belum dibuka.',
                'data' => [
                    'is_open' => false,
                    'period' => null,
                ],
            ]);
        }

        return response()->json([
            'message' => 'PPDB sedang dibuka.',
            'data' => [
                'is_open' => true,
                'is_kuota_penuh' => $activePeriod->isKuotaPenuh(),
                'period' => [
                    'id' => $activePeriod->id,
                    'nama_gelombang' => $activePeriod->nama_gelombang,
                    'tahun_ajaran' => $activePeriod->tahun_ajaran,
                    'tanggal_mulai' => $activePeriod->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $activePeriod->tanggal_selesai->toDateString(),
                    'kuota' => $activePeriod->kuota,
                    'jumlah_pendaftar' => $activePeriod->jumlahPendaftar(),
                    'biaya_pendaftaran' => $activePeriod->biaya_pendaftaran,
                    'deskripsi' => $activePeriod->deskripsi,
                ],
            ],
        ]);
    }

    /**
     * Statistik PPDB per gelombang.
     */
    public function statistik(Request $request)
    {
        $query = PpdbPeriod::query()->orderByDesc('created_at');

        if ($request->filled('tahun_ajaran')) {
            $query->where('tahun_ajaran', $request->tahun_ajaran);
        }

        $periods = $query->get()->map(function ($period) {
            $pendaftar = PpdbPendaftar::where('ppdb_period_id', $period->id);

            return [
                'id' => $period->id,
                'nama_gelombang' => $period->nama_gelombang,
                'tahun_ajaran' => $period->tahun_ajaran,
                'status' => $period->status,
                'kuota' => $period->kuota,
                'jumlah_pendaftar' => (clone $pendaftar)->count(),
                'jumlah_diterima' => (clone $pendaftar)->whereIn('status_verifikasi', ['diterima', 'lulus', 'accepted'])->count(),
                'jumlah_ditolak' => (clone $pendaftar)->where('status_verifikasi', 'ditolak')->count(),
                'jumlah_pending' => (clone $pendaftar)->whereNotIn('status_verifikasi', ['diterima', 'lulus', 'accepted', 'ditolak'])->count(),
            ];
        });

        return response()->json([
            'message' => 'Statistik PPDB berhasil dimuat.',
            'data' => $periods,
        ]);
    }
}
