<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataEkskul;
use App\Models\DataSantri;
use App\Models\PendaftaranEkskul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EkskulController extends Controller
{
    /**
     * GET /api/akademik/ekskul
     * List semua ekskul (dapat diakses admin dan santri).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $user = Auth::guard('sanctum')->user();

        $query = DataEkskul::query()
            ->with('unit:kode_unit,nama_unit')
            ->withCount('pendaftaran as jumlah_pendaftar')
            ->when($request->filled('kode_unit'), fn($q) => $q->where('kode_unit', strtoupper($request->kode_unit)))
            ->when($request->filled('status'), fn($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('status_pendaftaran'), fn($q) => $q->where('status_pendaftaran', strtoupper($request->status_pendaftaran)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('nama_ekskul', 'ilike', '%' . $request->q . '%');
            })
            ->orderBy('nama_ekskul');

        // Jika user adalah santri, filter ekskul agar hanya menampilkan yang:
        // 1. Berlaku untuk semua unit (kode_unit IS NULL) ATAU
        // 2. Sesuai dengan unit kelas santri tersebut
        if ($user instanceof \App\Models\DataAkunSantri) {
            $kodeUnitSantri = $this->resolveKodeUnitSantri($user);
            if ($kodeUnitSantri) {
                $query->where(function ($q) use ($kodeUnitSantri) {
                    $q->whereNull('kode_unit')
                      ->orWhere('kode_unit', $kodeUnitSantri);
                });
            }
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/akademik/ekskul
     * Buat ekskul baru (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_unit'          => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'nama_ekskul'        => ['required', 'string', 'max:100'],
            'deskripsi'          => ['nullable', 'string'],
            'kuota'              => ['nullable', 'integer', 'min:1'],
            'status'             => ['nullable', Rule::in(['AKTIF', 'NONAKTIF'])],
            'status_pendaftaran' => ['nullable', Rule::in(['BUKA', 'TUTUP'])],
        ]);

        if (isset($validated['kode_unit'])) {
            $validated['kode_unit'] = strtoupper($validated['kode_unit']);
        }
        $validated['status']             = strtoupper($validated['status'] ?? 'AKTIF');
        $validated['status_pendaftaran'] = strtoupper($validated['status_pendaftaran'] ?? 'TUTUP');

        $ekskul = DataEkskul::create($validated);
        $ekskul->load('unit:kode_unit,nama_unit');
        $ekskul->loadCount('pendaftaran as jumlah_pendaftar');

        return response()->json([
            'message' => 'Ekskul berhasil dibuat.',
            'data'    => $ekskul,
        ], 201);
    }

    /**
     * PUT /api/akademik/ekskul/{id}
     * Update ekskul (Admin). Termasuk toggle buka/tutup pendaftaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ekskul = DataEkskul::findOrFail($id);

        $validated = $request->validate([
            'kode_unit'          => ['sometimes', 'nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'nama_ekskul'        => ['sometimes', 'string', 'max:100'],
            'deskripsi'          => ['sometimes', 'nullable', 'string'],
            'kuota'              => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status'             => ['sometimes', Rule::in(['AKTIF', 'NONAKTIF'])],
            'status_pendaftaran' => ['sometimes', Rule::in(['BUKA', 'TUTUP'])],
        ]);

        if (array_key_exists('kode_unit', $validated) && $validated['kode_unit'] !== null) {
            $validated['kode_unit'] = strtoupper($validated['kode_unit']);
        }
        if (isset($validated['status'])) {
            $validated['status'] = strtoupper($validated['status']);
        }
        if (isset($validated['status_pendaftaran'])) {
            $validated['status_pendaftaran'] = strtoupper($validated['status_pendaftaran']);
        }

        $ekskul->update($validated);
        $ekskul->load('unit:kode_unit,nama_unit');
        $ekskul->loadCount('pendaftaran as jumlah_pendaftar');

        return response()->json([
            'message' => 'Ekskul berhasil diperbarui.',
            'data'    => $ekskul,
        ]);
    }

    /**
     * DELETE /api/akademik/ekskul/{id}
     * Hapus ekskul jika belum ada pendaftar (Admin).
     */
    public function destroy(int $id): JsonResponse
    {
        $ekskul = DataEkskul::withCount('pendaftaran as jumlah_pendaftar')->findOrFail($id);

        if ($ekskul->jumlah_pendaftar > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus ekskul karena sudah ada santri yang mendaftar.',
            ], 422);
        }

        $ekskul->delete();

        return response()->json(['message' => 'Ekskul berhasil dihapus.']);
    }

    /**
     * POST /api/akademik/ekskul/pendaftaran
     * Admin mendaftarkan santri ke ekskul.
     */
    public function storePendaftaran(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_santri' => ['required', 'integer', 'exists:data_santri,id_santri'],
            'id_ekskul' => ['required', 'integer', 'exists:data_ekskul,id_ekskul'],
        ]);

        $ekskul = DataEkskul::withCount('pendaftaran as jumlah_pendaftar')->findOrFail($request->id_ekskul);

        // Validasi kuota (optional untuk admin, tapi baiknya tetap dicek atau dilepas, kita cek saja)
        if ($ekskul->kuota !== null && $ekskul->jumlah_pendaftar >= $ekskul->kuota) {
            return response()->json(['message' => 'Kuota ekskul ini sudah penuh.'], 422);
        }

        // Cek apakah santri sudah punya pilihan
        $existing = PendaftaranEkskul::where('id_santri', $validated['id_santri'])->first();

        if ($existing) {
            if ($existing->id_ekskul === $request->id_ekskul) {
                return response()->json(['message' => 'Santri sudah terdaftar di ekskul ini.'], 422);
            }
            $existing->delete();
        }

        $pendaftaran = PendaftaranEkskul::create([
            'id_santri' => $validated['id_santri'],
            'id_ekskul' => $validated['id_ekskul'],
        ]);

        return response()->json([
            'message' => 'Pendaftaran berhasil ditambahkan.',
            'data'    => $pendaftaran,
        ], 201);
    }

    /**
     * PUT /api/akademik/ekskul/pendaftaran/{id}
     * Admin mengubah ekskul santri.
     */
    public function updatePendaftaran(Request $request, int $id): JsonResponse
    {
        $pendaftaran = PendaftaranEkskul::findOrFail($id);

        $validated = $request->validate([
            'id_ekskul' => ['required', 'integer', 'exists:data_ekskul,id_ekskul'],
        ]);

        if ($pendaftaran->id_ekskul === $validated['id_ekskul']) {
            return response()->json([
                'message' => 'Pendaftaran berhasil diperbarui.',
                'data'    => $pendaftaran,
            ]);
        }

        $ekskul = DataEkskul::withCount('pendaftaran as jumlah_pendaftar')->findOrFail($validated['id_ekskul']);

        if ($ekskul->kuota !== null && $ekskul->jumlah_pendaftar >= $ekskul->kuota) {
            return response()->json(['message' => 'Kuota ekskul ini sudah penuh.'], 422);
        }

        $pendaftaran->update(['id_ekskul' => $validated['id_ekskul']]);

        return response()->json([
            'message' => 'Pendaftaran berhasil diperbarui.',
            'data'    => $pendaftaran,
        ]);
    }

    /**
     * DELETE /api/akademik/ekskul/pendaftaran/{id}
     * Admin menghapus pendaftaran ekskul.
     */
    public function destroyPendaftaran(int $id): JsonResponse
    {
        $pendaftaran = PendaftaranEkskul::findOrFail($id);
        $pendaftaran->delete();

        return response()->json(['message' => 'Pendaftaran berhasil dihapus.']);
    }

    /**
     * GET /api/akademik/ekskul/rekap
     * Rekap seluruh pendaftar (Admin) + export Excel.
     */
    public function rekap(Request $request): JsonResponse|StreamedResponse
    {
        $query = PendaftaranEkskul::query()
            ->with([
                'santri:id_santri,nomor_induk,nama_lengkap_santri,kode_kelas',
                'ekskul:id_ekskul,nama_ekskul,kode_unit',
                'ekskul.unit:kode_unit,nama_unit',
            ])
            ->when($request->filled('id_ekskul'), fn($q) => $q->where('id_ekskul', $request->id_ekskul))
            ->when($request->filled('id_santri'), fn($q) => $q->where('id_santri', $request->id_santri))
            ->when($request->filled('nomor_induk'), function ($q) use ($request) {
                $q->whereHas('santri', fn($sq) => $sq->where('nomor_induk', $request->nomor_induk));
            })
            ->when($request->filled('kode_unit'), function ($q) use ($request) {
                $q->whereHas('ekskul', fn($eq) => $eq->where('kode_unit', strtoupper($request->kode_unit)));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->whereHas('santri', function ($sq) use ($request) {
                    $sq->where('nama_lengkap_santri', 'ilike', '%' . $request->q . '%')
                       ->orWhere('nomor_induk', 'ilike', '%' . $request->q . '%');
                });
            })
            ->orderBy('created_at', 'desc');

        if ($request->boolean('export')) {
            return $this->exportRekap($query);
        }

        $perPage = (int) $request->query('per_page', 25);
        return response()->json($query->paginate($perPage));
    }

    private function exportRekap($query): StreamedResponse
    {
        $rows = $query->get();

        $headers = ['No', 'Nomor Induk', 'Nama Santri', 'Kelas', 'Ekskul', 'Unit', 'Tanggal Daftar'];

        return response()->streamDownload(function () use ($rows, $headers) {
            $output = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fputs($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);

            $no = 1;
            foreach ($rows as $row) {
                fputcsv($output, [
                    $no++,
                    $row->santri?->nomor_induk ?? '-',
                    $row->santri?->nama_lengkap_santri ?? '-',
                    $row->santri?->kode_kelas ?? '-',
                    $row->ekskul?->nama_ekskul ?? '-',
                    $row->ekskul?->unit?->nama_unit ?? $row->ekskul?->kode_unit ?? '-',
                    $row->created_at?->format('d/m/Y H:i') ?? '-',
                ]);
            }

            fclose($output);
        }, 'rekap-pendaftar-ekskul-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * GET /api/akademik/ekskul/pilihan-saya
     * Ekskul yang dipilih santri yang sedang login.
     */
    public function pilihanSaya(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        // Resolve id_santri (bisa dari akun santri atau langsung)
        $idSantri = $this->resolveIdSantri($user);

        if (!$idSantri) {
            return response()->json(['data' => null]);
        }

        $pendaftaran = PendaftaranEkskul::with([
            'ekskul:id_ekskul,nama_ekskul,deskripsi,kode_unit,kuota,status,status_pendaftaran',
            'ekskul.unit:kode_unit,nama_unit',
        ])->where('id_santri', $idSantri)->first();

        return response()->json(['data' => $pendaftaran]);
    }

    /**
     * POST /api/akademik/ekskul/daftar
     * Santri mendaftar ekskul.
     */
    public function daftar(Request $request): JsonResponse
    {
        $request->validate([
            'id_ekskul' => ['required', 'integer', 'exists:data_ekskul,id_ekskul'],
        ]);

        $user     = Auth::guard('sanctum')->user();
        $idSantri = $this->resolveIdSantri($user);

        if (!$idSantri) {
            return response()->json(['message' => 'Akun santri tidak ditemukan.'], 403);
        }

        $ekskul = DataEkskul::withCount('pendaftaran as jumlah_pendaftar')->findOrFail($request->id_ekskul);

        // Validasi: status ekskul harus AKTIF
        if (strtoupper($ekskul->status) !== 'AKTIF') {
            return response()->json(['message' => 'Ekskul ini tidak aktif.'], 422);
        }

        // Validasi: pendaftaran harus BUKA
        if (strtoupper($ekskul->status_pendaftaran) !== 'BUKA') {
            return response()->json(['message' => 'Pendaftaran ekskul ini sudah ditutup.'], 422);
        }

        // Validasi: kuota
        if ($ekskul->kuota !== null && $ekskul->jumlah_pendaftar >= $ekskul->kuota) {
            return response()->json(['message' => 'Kuota ekskul ini sudah penuh.'], 422);
        }

        // Cek apakah santri sudah punya pilihan — HARUS batalkan dulu
        $existing = PendaftaranEkskul::with('ekskul:id_ekskul,nama_ekskul')
            ->where('id_santri', $idSantri)->first();

        if ($existing) {
            if ($existing->id_ekskul === (int) $request->id_ekskul) {
                return response()->json(['message' => 'Kamu sudah terdaftar di ekskul ini.'], 422);
            }
            return response()->json([
                'message' => "Kamu sudah terdaftar di ekskul \"{$existing->ekskul?->nama_ekskul}\". Batalkan pilihan tersebut terlebih dahulu sebelum memilih ekskul lain.",
            ], 422);
        }

        $pendaftaran = PendaftaranEkskul::create([
            'id_santri' => $idSantri,
            'id_ekskul' => $request->id_ekskul,
        ]);

        $pendaftaran->load([
            'ekskul:id_ekskul,nama_ekskul,deskripsi,kode_unit',
            'ekskul.unit:kode_unit,nama_unit',
        ]);

        return response()->json([
            'message' => "Berhasil mendaftar ke ekskul {$ekskul->nama_ekskul}.",
            'data'    => $pendaftaran,
        ], 201);
    }

    /**
     * POST /api/akademik/ekskul/batal
     * Santri membatalkan pilihan ekskul.
     */
    public function batal(Request $request): JsonResponse
    {
        $user     = Auth::guard('sanctum')->user();
        $idSantri = $this->resolveIdSantri($user);

        if (!$idSantri) {
            return response()->json(['message' => 'Akun santri tidak ditemukan.'], 403);
        }

        $pendaftaran = PendaftaranEkskul::with('ekskul:id_ekskul,nama_ekskul')
            ->where('id_santri', $idSantri)->first();

        if (!$pendaftaran) {
            return response()->json(['message' => 'Kamu belum terdaftar di ekskul manapun.'], 404);
        }

        $namaEkskul = $pendaftaran->ekskul->nama_ekskul;
        $pendaftaran->delete();

        return response()->json(['message' => "Pilihan ekskul {$namaEkskul} berhasil dibatalkan."]);
    }

    /**
     * Resolve id_santri dari guard yang digunakan (petugas/santri).
     */
    private function resolveIdSantri($user): ?int
    {
        if (!$user) return null;

        // Jika user adalah akun santri
        if ($user instanceof \App\Models\DataAkunSantri) {
            $santri = DataSantri::where('nomor_induk', $user->nomor_induk)->first();
            return $santri?->id_santri;
        }

        // Jika ada field id_santri langsung
        if (isset($user->id_santri)) {
            return (int) $user->id_santri;
        }

        return null;
    }

    /**
     * Resolve kode_unit santri berdasarkan kelas saat ini.
     */
    private function resolveKodeUnitSantri($user): ?string
    {
        if (!$user) return null;

        if ($user instanceof \App\Models\DataAkunSantri) {
            $santri = DataSantri::with('kelas:kode_kelas,kode_unit')
                ->where('nomor_induk', $user->nomor_induk)
                ->first();
            return $santri?->kelas?->kode_unit;
        }

        return null;
    }
}
