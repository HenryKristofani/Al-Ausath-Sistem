<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\AdministrasiBebas;
use App\Models\AdministrasiBebasPembayaran;
use App\Models\DataPetugas;
use App\Models\DataSantri;
use App\Models\KwitansiPdf;
use App\Support\KwitansiPdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdministrasiBebasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Tampilkan daftar tagihan bebas.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $search = $request->query('search');
        $idSantri = $request->query('id_santri');
        $status = $request->query('status');

        $query = AdministrasiBebas::with(['santri.kelas.unit'])
            ->when($idSantri, function ($q) use ($idSantri) {
                $q->where('id_santri', $idSantri);
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', strtoupper($status));
            })
            ->when($search, function ($q) use ($search) {
                $q->whereHas('santri', function ($sub) use ($search) {
                    $sub->where('nama_lengkap_santri', 'like', "%{$search}%")
                        ->orWhere('nomor_induk', 'like', "%{$search}%");
                })->orWhere('deskripsi', 'like', "%{$search}%");
            })
            ->orderBy('id_admin_bebas', 'desc');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan tagihan bebas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_santri' => ['required', 'integer', 'exists:data_santri,id_santri'],
            'deskripsi' => ['required', 'string', 'max:255'],
            'total_tagihan' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['sisa'] = $validated['total_tagihan'];
        $validated['status'] = 'BELUM_LUNAS';

        $data = AdministrasiBebas::create($validated);

        return response()->json([
            'message' => 'Tagihan bebas berhasil dibuat.',
            'data' => $data->load('santri'),
        ], 201);
    }

    /**
     * Tampilkan detail tagihan bebas.
     */
    public function show(int $id): JsonResponse
    {
        $data = AdministrasiBebas::with(['santri.kelas.unit', 'pembayaran.petugas', 'kwitansi'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui tagihan bebas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tagihan = AdministrasiBebas::findOrFail($id);

        $validated = $request->validate([
            'deskripsi' => ['sometimes', 'string', 'max:255'],
            'total_tagihan' => ['sometimes', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('total_tagihan', $validated)) {
            $diff = $validated['total_tagihan'] - $tagihan->total_tagihan;
            $newSisa = $tagihan->sisa + $diff;

            if ($newSisa < 0) {
                return response()->json([
                    'message' => 'Total tagihan tidak boleh lebih kecil dari jumlah yang sudah dibayar.',
                ], 422);
            }

            $validated['sisa'] = $newSisa;
            $validated['status'] = $newSisa == 0 ? 'LUNAS' : 'BELUM_LUNAS';
        }

        $tagihan->update($validated);

        return response()->json([
            'message' => 'Tagihan bebas berhasil diperbarui.',
            'data' => $tagihan->fresh('santri'),
        ]);
    }

    /**
     * Hapus tagihan bebas beserta pembayarannya.
     */
    public function destroy(int $id): JsonResponse
    {
        $tagihan = AdministrasiBebas::findOrFail($id);

        // Check if there are completed payments to prevent accidental deletion
        if ($tagihan->pembayaran()->count() > 0) {
            return response()->json([
                'message' => 'Tagihan tidak dapat dihapus karena sudah memiliki data pembayaran.',
            ], 422);
        }

        $tagihan->delete();

        return response()->json([
            'message' => 'Tagihan bebas berhasil dihapus.',
        ]);
    }

    /**
     * Catat pembayaran (cicilan) untuk tagihan bebas.
     */
    public function storePembayaran(Request $request, int $id): JsonResponse
    {
        $tagihan = AdministrasiBebas::findOrFail($id);

        $validated = $request->validate([
            'nominal_bayar' => ['required', 'numeric', 'min:1'],
            'metode_bayar' => ['required', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
        ]);

        if ($validated['nominal_bayar'] > $tagihan->sisa) {
            return response()->json([
                'message' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan (' . number_format($tagihan->sisa, 0, ',', '.') . ').',
            ], 422);
        }

        $user = $request->user();
        $idPetugas = $user ? $user->id_petugas : null;

        $pembayaran = DB::transaction(function () use ($tagihan, $validated, $idPetugas) {
            // Create payment
            $pay = AdministrasiBebasPembayaran::create([
                'id_admin_bebas' => $tagihan->id_admin_bebas,
                'id_petugas' => $idPetugas,
                'nominal_bayar' => $validated['nominal_bayar'],
                'tanggal_bayar' => now(),
                'metode_bayar' => $validated['metode_bayar'],
                'keterangan' => $validated['keterangan'] ?? 'Pembayaran cicilan bebas',
            ]);

            // Update tagihan
            $newTerbayar = $tagihan->total_tagihan - $tagihan->sisa + $validated['nominal_bayar'];
            $newSisa = $tagihan->sisa - $validated['nominal_bayar'];
            
            $tagihan->update([
                'sisa' => $newSisa,
                'status' => $newSisa == 0 ? 'LUNAS' : 'BELUM_LUNAS',
            ]);

            // Generate Kwitansi PDF
            $fileName = 'kwitansi/bebas_' . $pay->id_bayar_bebas . '_' . time() . '.pdf';
            $kwitansi = KwitansiPdf::create([
                'id_pembayaran' => null,
                'id_admin_bebas' => $tagihan->id_admin_bebas,
                'id_petugas' => $idPetugas,
                'jenis' => 'BEBAS',
                'jumlah' => $pay->nominal_bayar,
                'file_path_pdf' => $fileName,
            ]);

            $this->ensureKwitansiPdf($kwitansi, $tagihan, $pay, $idPetugas);

            return $pay;
        });

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => $pembayaran->load('administrasiBebas'),
        ], 201);
    }

    /**
     * Download PDF Kwitansi Pembayaran Bebas.
     */
    public function downloadKwitansi(int $idKwitansi): mixed
    {
        $kwitansi = KwitansiPdf::where('jenis', 'BEBAS')->findOrFail($idKwitansi);
        
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (!$disk->exists((string) $kwitansi->file_path_pdf)) {
            // Re-generate if missing
            $tagihan = AdministrasiBebas::with('santri.kelas.unit')->findOrFail($kwitansi->id_admin_bebas);
            
            // Find the payment corresponding to this kwitansi's nominal
            $pembayaran = AdministrasiBebasPembayaran::where('id_admin_bebas', $tagihan->id_admin_bebas)
                ->where('nominal_bayar', $kwitansi->jumlah)
                ->orderBy('id_bayar_bebas', 'desc')
                ->first();

            if ($pembayaran) {
                $this->ensureKwitansiPdf($kwitansi, $tagihan, $pembayaran, $kwitansi->id_petugas);
            } else {
                return response()->json(['message' => 'Pembayaran tidak ditemukan untuk kwitansi ini.'], 404);
            }
        }

        return response()->download($disk->path((string) $kwitansi->file_path_pdf));
    }

    private function ensureKwitansiPdf(KwitansiPdf $kwitansi, AdministrasiBebas $tagihan, AdministrasiBebasPembayaran $pembayaran, ?int $idPetugas): string
    {
        return app(KwitansiPdfGenerator::class)->generate(
            (string) $kwitansi->file_path_pdf,
            $this->buildKwitansiPayload($tagihan, $pembayaran, $kwitansi, $idPetugas)
        );
    }

    private function buildKwitansiPayload(AdministrasiBebas $tagihan, AdministrasiBebasPembayaran $pembayaran, KwitansiPdf $kwitansi, ?int $idPetugas): array
    {
        $santri     = $tagihan->santri;
        $nama       = $santri?->nama_lengkap_santri ?? '-';
        $nomorInduk = $santri?->nomor_induk ?? '-';
        $unit       = $santri?->kelas?->unit?->nama_unit ?? $santri?->kelas?->kode_unit ?? '-';
        $kelas      = $santri?->kelas?->nama_kelas ?? '-';

        // Lookup petugas name
        $namaPetugas = 'Petugas Keuangan';
        if ($idPetugas) {
            $petugas = DataPetugas::find($idPetugas);
            if ($petugas) {
                $namaPetugas = $petugas->nama_lengkap;
            }
        }

        // Sisa tagihan
        $sisaStr = $tagihan->sisa > 0
            ? 'Rp ' . number_format($tagihan->sisa, 0, ',', '.')
            : 'Rp 0';

        // Rincian tagihan
        $rincian = trim($tagihan->deskripsi . ($pembayaran->keterangan ? ' — ' . $pembayaran->keterangan : ''));

        return [
            'title'          => 'Kwitansi Pembayaran Bebas',
            'jenis'          => 'BEBAS',
            'nomor_kwitansi' => str_pad((string) $kwitansi->id_kwitansi, 5, '0', STR_PAD_LEFT),
            'nomor_invoice'  => 'INV-BEBAS-' . str_pad((string) $tagihan->id_admin_bebas, 5, '0', STR_PAD_LEFT),
            'tanggal'        => optional($pembayaran->tanggal_bayar ?? $pembayaran->created_at)->format('d/m/Y H:i'),
            'nama'           => $nama,
            'nomor_induk'    => $nomorInduk,
            'unit'           => $unit,
            'kelas'          => $kelas,
            'bulan'          => '',
            'periode'        => '',
            'rincian'        => $rincian ?: 'Pembayaran Administrasi Bebas',
            'metode'         => $pembayaran->metode_bayar ?? 'Tunai',
            'status'         => $tagihan->status === 'LUNAS' ? 'Lunas' : 'Belum Lunas',
            'nominal'        => 'Rp ' . number_format((float) ($pembayaran->nominal_bayar ?? 0), 0, ',', '.'),
            'nominal_raw'    => (float) ($pembayaran->nominal_bayar ?? 0),
            'sisa_tagihan'   => $sisaStr,
            'nama_petugas'   => $namaPetugas,
        ];
    }
}
