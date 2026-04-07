<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\AdministrasiBebas;
use App\Models\AdministrasiBebasPembayaran;
use App\Models\KwitansiPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdministrasiBebasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List tagihan administrasi bebas.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = AdministrasiBebas::query()
            ->with(['santri', 'pembayaran.petugas'])
            ->when($request->filled('id_santri'), fn ($q) => $q->where('id_santri', $request->id_santri))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('deskripsi', 'like', "%{$keyword}%")
                        ->orWhereHas('santri', fn ($santriQuery) => $santriQuery
                            ->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                            ->orWhere('nomor_induk', 'like', "%{$keyword}%"));
                });
            })
            ->orderByDesc('id_admin_bebas');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Buat tagihan administrasi bebas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_santri' => ['required', 'integer', 'exists:data_santri,id_santri'],
            'deskripsi' => ['required', 'string'],
            'total_tagihan' => ['required', 'numeric', 'min:0'],
            'sisa' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $sisa = array_key_exists('sisa', $validated)
            ? (float) $validated['sisa']
            : (float) $validated['total_tagihan'];

        if ($sisa > (float) $validated['total_tagihan']) {
            return response()->json([
                'message' => 'Nilai sisa tidak boleh melebihi total tagihan.',
            ], 422);
        }

        $data = AdministrasiBebas::create([
            'id_santri' => $validated['id_santri'],
            'deskripsi' => $validated['deskripsi'],
            'total_tagihan' => $validated['total_tagihan'],
            'sisa' => $sisa,
            'status' => $validated['status'] ?? ($sisa <= 0 ? 'lunas' : 'tagihan'),
        ]);

        return response()->json([
            'message' => 'Tagihan administrasi bebas berhasil dibuat.',
            'data' => $data->fresh(['santri']),
        ], 201);
    }

    /**
     * Detail tagihan administrasi bebas.
     */
    public function show(int $id): JsonResponse
    {
        $data = AdministrasiBebas::with(['santri', 'pembayaran.petugas'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui tagihan administrasi bebas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $administrasi = AdministrasiBebas::with('pembayaran')->findOrFail($id);

        $validated = $request->validate([
            'id_santri' => ['sometimes', 'integer', 'exists:data_santri,id_santri'],
            'deskripsi' => ['sometimes', 'string'],
            'total_tagihan' => ['sometimes', 'numeric', 'min:0'],
            'sisa' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        $totalTagihanAkhir = (float) ($validated['total_tagihan'] ?? $administrasi->total_tagihan);
        $totalTerbayar = (float) $administrasi->pembayaran->sum('nominal_bayar');

        if ($totalTagihanAkhir < $totalTerbayar) {
            return response()->json([
                'message' => 'Total tagihan tidak boleh lebih kecil dari total cicilan yang sudah dibayar.',
            ], 422);
        }

        if (!array_key_exists('sisa', $validated)) {
            $validated['sisa'] = max($totalTagihanAkhir - $totalTerbayar, 0);
        }

        if (!array_key_exists('status', $validated)) {
            $validated['status'] = ((float) $validated['sisa'] <= 0) ? 'lunas' : 'cicilan';
        }

        $administrasi->update($validated);

        return response()->json([
            'message' => 'Tagihan administrasi bebas berhasil diperbarui.',
            'data' => $administrasi->fresh(['santri', 'pembayaran.petugas']),
        ]);
    }

    /**
     * Hapus tagihan administrasi bebas.
     */
    public function destroy(int $id): JsonResponse
    {
        $administrasi = AdministrasiBebas::withCount('pembayaran')->findOrFail($id);

        if ($administrasi->pembayaran_count > 0) {
            return response()->json([
                'message' => 'Tagihan tidak dapat dihapus karena sudah memiliki data cicilan.',
            ], 422);
        }

        $administrasi->delete();

        return response()->json([
            'message' => 'Tagihan administrasi bebas berhasil dihapus.',
        ]);
    }

    /**
     * Catat cicilan administrasi bebas dan buat data kwitansi.
     */
    public function bayarCicilan(Request $request, int $id): JsonResponse
    {
        $administrasi = AdministrasiBebas::findOrFail($id);

        $validated = $request->validate([
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'nominal_bayar' => ['required', 'numeric', 'min:1'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
        ]);

        if ((float) $administrasi->sisa <= 0) {
            return response()->json([
                'message' => 'Tagihan sudah lunas.',
            ], 422);
        }

        if ((float) $validated['nominal_bayar'] > (float) $administrasi->sisa) {
            return response()->json([
                'message' => 'Nominal cicilan melebihi sisa tagihan.',
            ], 422);
        }

        [$pembayaran, $kwitansi, $sisaBaru, $statusBaru] = DB::transaction(function () use ($validated, $administrasi) {
            $pembayaran = AdministrasiBebasPembayaran::create([
                'id_admin_bebas' => $administrasi->id_admin_bebas,
                'id_petugas' => $validated['id_petugas'] ?? null,
                'nominal_bayar' => $validated['nominal_bayar'],
                'tanggal_bayar' => isset($validated['tanggal_bayar'])
                    ? Carbon::parse($validated['tanggal_bayar'])
                    : now(),
                'metode_bayar' => $validated['metode_bayar'] ?? null,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            $totalBayar = (float) AdministrasiBebasPembayaran::query()
                ->where('id_admin_bebas', $administrasi->id_admin_bebas)
                ->sum('nominal_bayar');

            $sisaBaru = max(((float) $administrasi->total_tagihan) - $totalBayar, 0);
            $statusBaru = $sisaBaru <= 0 ? 'lunas' : 'cicilan';

            $administrasi->update([
                'sisa' => $sisaBaru,
                'status' => $statusBaru,
            ]);

            $filePath = 'kwitansi/administrasi-bebas/'
                . $administrasi->id_admin_bebas
                . '/kwitansi-'
                . $pembayaran->id_bayar_bebas
                . '.pdf';

            $kwitansi = KwitansiPdf::create([
                'id_admin_bebas' => $administrasi->id_admin_bebas,
                'id_petugas' => $validated['id_petugas'] ?? null,
                'jenis' => 'administrasi_bebas',
                'jumlah' => $validated['nominal_bayar'],
                'file_path_pdf' => $filePath,
            ]);

            return [$pembayaran, $kwitansi, $sisaBaru, $statusBaru];
        });

        return response()->json([
            'message' => 'Cicilan administrasi bebas berhasil dicatat.',
            'data' => [
                'pembayaran' => $pembayaran,
                'kwitansi' => $kwitansi,
                'sisa_tagihan' => $sisaBaru,
                'status_tagihan' => $statusBaru,
            ],
        ], 201);
    }
}
