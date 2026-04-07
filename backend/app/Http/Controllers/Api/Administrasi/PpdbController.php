<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
use App\Models\DataKelas;
use App\Models\DataSantri;
use App\Models\PpdbBerkas;
use App\Models\PpdbNotifikasi;
use App\Models\PpdbPendaftar;
use App\Models\PpdbTes;
use App\Models\PpdbVerifikasi;
use App\Support\PpdbRegistrationNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class PpdbController extends Controller
{
    private PpdbRegistrationNumberService $nomorService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->nomorService = app(PpdbRegistrationNumberService::class);
    }

    /**
     * List data pendaftar PPDB.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = PpdbPendaftar::query()
            ->with(['akun', 'santriDiterima', 'berkas', 'tes', 'verifikasi', 'notifikasi'])
            ->when($request->filled('status_verifikasi'), fn ($q) => $q->where('status_verifikasi', $request->status_verifikasi))
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', $request->jenjang))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_calon', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk_generated', 'like', "%{$keyword}%")
                        ->orWhere('asal_kota', 'like', "%{$keyword}%")
                        ->orWhere('nomor_umi', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_pendaftaran');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data pendaftar PPDB baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_akun' => ['nullable', 'integer', 'exists:akun_pendaftar,id_akun'],
            'nama_calon' => ['required', 'string', 'max:200'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nomor_umi' => [
                Rule::requiredIf(fn () => mb_strtolower((string) $request->jenjang) === 'smp'),
                'nullable',
                'string',
                'max:50',
            ],
            'asal_kota' => ['nullable', 'string', 'max:100'],
            'is_luar_kota' => ['nullable', 'boolean'],
            'status_verifikasi' => ['nullable', 'string', 'max:30'],
            'tanggal_daftar' => ['nullable', 'date'],
            'tanggal_pengumuman' => ['nullable', 'date'],
        ]);

        $tanggalDaftar = isset($validated['tanggal_daftar'])
            ? Carbon::parse($validated['tanggal_daftar'])
            : now();

        $idPendaftaran = $this->nomorService->generatePendaftaranId($tanggalDaftar);
        $validated['no_pendaftaran'] = $this->nomorService->generateInitialNumber($tanggalDaftar);
        $validated['no_pendaftaran_final'] = $this->nomorService->generateFinalNumber($validated['no_pendaftaran']);

        if (!array_key_exists('is_luar_kota', $validated)) {
            $validated['is_luar_kota'] = $this->nomorService->isLuarKota($validated['asal_kota'] ?? null);
        }

        $validated['status_verifikasi'] = $validated['status_verifikasi'] ?? 'pending';
        $validated['tanggal_daftar'] = $tanggalDaftar->toDateString();

        $data = new PpdbPendaftar($validated);
        $data->id_pendaftaran = $idPendaftaran;
        $data->save();

        return response()->json([
            'message' => 'Data pendaftar PPDB berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail pendaftar PPDB.
     */
    public function show(int $id): JsonResponse
    {
        $data = PpdbPendaftar::with(['akun', 'santriDiterima', 'berkas', 'tes', 'verifikasi', 'notifikasi'])
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data pendaftar PPDB.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'id_akun' => ['nullable', 'integer', 'exists:akun_pendaftar,id_akun'],
            'no_pendaftaran' => ['sometimes', 'string', 'max:50', 'unique:ppdb_pendaftar,no_pendaftaran,' . $pendaftar->id_pendaftaran . ',id_pendaftaran'],
            'no_pendaftaran_final' => ['nullable', 'string', 'max:50', 'unique:ppdb_pendaftar,no_pendaftaran_final,' . $pendaftar->id_pendaftaran . ',id_pendaftaran'],
            'nama_calon' => ['sometimes', 'string', 'max:200'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nomor_umi' => ['nullable', 'string', 'max:50'],
            'asal_kota' => ['nullable', 'string', 'max:100'],
            'is_luar_kota' => ['nullable', 'boolean'],
            'status_verifikasi' => ['nullable', 'string', 'max:30'],
            'tanggal_daftar' => ['nullable', 'date'],
            'tanggal_pengumuman' => ['nullable', 'date'],
        ]);

        if (
            $pendaftar->nomor_induk_generated
            && (array_key_exists('no_pendaftaran', $validated) || array_key_exists('no_pendaftaran_final', $validated))
        ) {
            return response()->json([
                'message' => 'Nomor pendaftaran tidak dapat diubah setelah nomor induk santri digenerate.',
            ], 422);
        }

        $jenjangAkhir = mb_strtolower((string) ($validated['jenjang'] ?? $pendaftar->jenjang));
        $nomorUmiAkhir = $validated['nomor_umi'] ?? $pendaftar->nomor_umi;

        if ($jenjangAkhir === 'smp' && ($nomorUmiAkhir === null || trim((string) $nomorUmiAkhir) === '')) {
            return response()->json([
                'message' => 'Nomor UMI wajib diisi untuk jenjang SMP.',
            ], 422);
        }

        if (array_key_exists('asal_kota', $validated) && !array_key_exists('is_luar_kota', $validated)) {
            $validated['is_luar_kota'] = $this->nomorService->isLuarKota($validated['asal_kota']);
        }

        if (empty($validated['no_pendaftaran_final']) && empty($pendaftar->no_pendaftaran_final)) {
            $baseNumber = $validated['no_pendaftaran'] ?? $pendaftar->no_pendaftaran;

            if ($baseNumber) {
                $validated['no_pendaftaran_final'] = $this->nomorService->generateFinalNumber($baseNumber);
            }
        }

        $pendaftar->update($validated);

        return response()->json([
            'message' => 'Data pendaftar PPDB berhasil diperbarui.',
            'data' => $pendaftar->fresh(['akun', 'santriDiterima', 'berkas', 'tes', 'verifikasi', 'notifikasi']),
        ]);
    }

    /**
     * Hapus data pendaftar PPDB.
     */
    public function destroy(int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::findOrFail($id);

        if ($pendaftar->id_santri) {
            return response()->json([
                'message' => 'Data pendaftar tidak dapat dihapus karena sudah terintegrasi menjadi data santri.',
            ], 422);
        }

        DB::transaction(function () use ($pendaftar) {
            PpdbNotifikasi::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();
            PpdbVerifikasi::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();
            PpdbTes::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();
            PpdbBerkas::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();

            $pendaftar->delete();
        });

        return response()->json([
            'message' => 'Data pendaftar PPDB berhasil dihapus.',
        ]);
    }

    /**
     * Tambahkan berkas PPDB untuk pendaftar.
     */
    public function storeBerkas(Request $request, int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::findOrFail($id);

        if (!$this->isBisaUploadBerkas($pendaftar)) {
            return response()->json([
                'message' => 'Pendaftar dalam kota Karanganyar dimohon datang langsung ke kantor. Upload berkas hanya untuk pendaftar luar kota.',
            ], 422);
        }

        $validated = $request->validate([
            'jenis_berkas' => ['required', 'string', 'max:80'],
            'file_path' => ['required', 'string'],
            'uploaded_at' => ['nullable', 'date'],
        ]);

        $berkas = PpdbBerkas::create([
            'id_pendaftaran' => $id,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Berkas PPDB berhasil ditambahkan.',
            'data' => $berkas,
        ], 201);
    }

    /**
     * Simpan atau perbarui hasil tes PPDB.
     */
    public function upsertTes(Request $request, int $id): JsonResponse
    {
        PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'nilai' => ['nullable', 'numeric'],
            'status_tes' => ['nullable', 'string', 'max:30'],
            'metode_tes' => ['nullable', 'string', 'max:50'],
            'soal_tes' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        $tes = PpdbTes::updateOrCreate(
            ['id_pendaftaran' => $id],
            $validated
        );

        return response()->json([
            'message' => 'Hasil tes PPDB berhasil disimpan.',
            'data' => $tes,
        ]);
    }

    /**
     * Simpan atau perbarui verifikasi PPDB.
     */
    public function upsertVerifikasi(Request $request, int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::with('akun')->findOrFail($id);

        $hasilInput = mb_strtolower((string) $request->input('hasil', ''));

        $validated = $request->validate([
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tanggal_verif' => ['nullable', 'date'],
            'hasil' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'kode_kelas_diterima' => [
                Rule::requiredIf(fn () => $this->isStatusDiterima($hasilInput)),
                'nullable',
                'string',
                'exists:data_kelas,kode_kelas',
            ],
            'auto_buat_akun_santri' => ['nullable', 'boolean'],
        ]);

        $validated['tanggal_verif'] = isset($validated['tanggal_verif'])
            ? Carbon::parse($validated['tanggal_verif'])
            : now();

        $autoBuatAkunSantri = $validated['auto_buat_akun_santri'] ?? true;

        unset($validated['auto_buat_akun_santri']);

        $payloadVerifikasi = $validated;
        unset($payloadVerifikasi['kode_kelas_diterima']);

        $integrasiDiterima = null;

        $verifikasi = DB::transaction(function () use ($id, $validated, $payloadVerifikasi, $pendaftar, $autoBuatAkunSantri, &$integrasiDiterima) {
            $verifikasi = PpdbVerifikasi::updateOrCreate(
                ['id_pendaftaran' => $id],
                $payloadVerifikasi
            );

            if (!empty($validated['hasil'])) {
                $pendaftar->update([
                    'status_verifikasi' => $validated['hasil'],
                    'tanggal_pengumuman' => $pendaftar->tanggal_pengumuman
                        ? $pendaftar->tanggal_pengumuman
                        : Carbon::parse($validated['tanggal_verif'])->toDateString(),
                ]);
            }

            if ($this->isStatusDiterima($validated['hasil'] ?? null)) {
                $integrasiDiterima = $this->integrasikanPendaftarDiterima(
                    $pendaftar,
                    (string) ($validated['kode_kelas_diterima'] ?? ''),
                    $autoBuatAkunSantri
                );
            }

            return $verifikasi;
        });

        return response()->json([
            'message' => 'Verifikasi manual PPDB berhasil disimpan.',
            'data' => $verifikasi,
            'integrasi_diterima' => $integrasiDiterima,
        ]);
    }

    /**
     * Tambahkan notifikasi PPDB untuk pendaftar.
     */
    public function storeNotifikasi(Request $request, int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::with('akun')->findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:20'],
            'konten' => ['required', 'string'],
            'sent_at' => ['nullable', 'date'],
            'status_kirim' => ['nullable', 'string', 'max:20'],
            'kirim_email' => ['nullable', 'boolean'],
        ]);

        $notifikasi = PpdbNotifikasi::create([
            'id_pendaftaran' => $id,
            'type' => $validated['type'],
            'konten' => $validated['konten'],
            'sent_at' => isset($validated['sent_at']) ? Carbon::parse($validated['sent_at']) : null,
            'status_kirim' => $validated['status_kirim'] ?? 'tersimpan',
        ]);

        $emailStatus = null;
        $emailError = null;

        $shouldSendEmail = ($validated['kirim_email'] ?? false)
            || mb_strtolower((string) $validated['type']) === 'email';

        if ($shouldSendEmail) {
            if (empty($pendaftar->akun?->email)) {
                $emailStatus = 'gagal';
                $emailError = 'Email pendaftar tidak tersedia.';
            } else {
                try {
                    Mail::raw($validated['konten'], function ($message) use ($pendaftar) {
                        $message
                            ->to($pendaftar->akun->email)
                            ->subject('Notifikasi PPDB ' . ($pendaftar->no_pendaftaran_final ?: $pendaftar->no_pendaftaran));
                    });

                    $emailStatus = 'terkirim';
                } catch (Throwable $exception) {
                    $emailStatus = 'gagal';
                    $emailError = 'Gagal mengirim email notifikasi.';
                }
            }

            $notifikasi->update([
                'status_kirim' => $emailStatus,
                'sent_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Notifikasi PPDB berhasil dibuat.',
            'data' => $notifikasi->fresh(),
            'email_status' => $emailStatus,
            'email_error' => $emailError,
        ], 201);
    }

    private function isStatusDiterima(?string $hasil): bool
    {
        $normalized = mb_strtolower(trim((string) $hasil));

        return in_array($normalized, ['diterima', 'lulus', 'accepted'], true);
    }

    private function integrasikanPendaftarDiterima(
        PpdbPendaftar $pendaftar,
        string $kodeKelasDiterima,
        bool $autoBuatAkunSantri
    ): array {
        $nomorInduk = $pendaftar->nomor_induk_generated
            ?: $this->nomorService->generateNomorIndukFromPendaftaran($pendaftar);

        $kelas = DataKelas::where('kode_kelas', $kodeKelasDiterima)->firstOrFail();

        $santri = DataSantri::firstOrNew(['nomor_induk' => $nomorInduk]);

        $santri->fill([
            'nama_lengkap_santri' => $pendaftar->nama_calon,
            'kode_kelas' => $kodeKelasDiterima,
            'status' => 'AKTIF',
            'tahun_masuk' => $santri->tahun_masuk ?: (int) now()->format('Y'),
            'nomor_telepon' => $pendaftar->akun?->phone,
            'alamat_email' => $pendaftar->akun?->email,
        ]);

        $santri->save();

        $akunSantri = null;
        $passwordDefault = null;

        if ($autoBuatAkunSantri) {
            $passwordHash = $pendaftar->akun?->password_hash;

            if (!$passwordHash) {
                $passwordDefault = $nomorInduk;
                $passwordHash = Hash::make($passwordDefault);
            }

            $akunSantri = DataAkunSantri::updateOrCreate(
                ['nomor_induk' => $nomorInduk],
                [
                    'nama_akun' => $nomorInduk,
                    'nama_lengkap' => $pendaftar->nama_calon,
                    'nama_unit' => $kelas->kode_unit,
                    'nama_kelas' => $kelas->nama_kelas,
                    'tahun_ajaran' => $kelas->tahun_ajaran,
                    'alamat_email' => $pendaftar->akun?->email,
                    'nomor_telepon' => $pendaftar->akun?->phone,
                    'password_hash' => $passwordHash,
                    'status' => 'AKTIF',
                ]
            );
        }

        $pendaftar->update([
            'id_santri' => $santri->id_santri,
            'nomor_induk_generated' => $nomorInduk,
            'kode_kelas_diterima' => $kodeKelasDiterima,
            'tanggal_diterima' => now()->toDateString(),
            'status_verifikasi' => 'diterima',
        ]);

        return [
            'nomor_induk_generated' => $nomorInduk,
            'id_santri' => $santri->id_santri,
            'kode_kelas_diterima' => $kodeKelasDiterima,
            'akun_santri' => $akunSantri ? [
                'id_akun_santri' => $akunSantri->id_akun_santri,
                'nama_akun' => $akunSantri->nama_akun,
                'nomor_induk' => $akunSantri->nomor_induk,
                'password_default' => $passwordDefault,
            ] : null,
        ];
    }

    private function isBisaUploadBerkas(PpdbPendaftar $pendaftar): bool
    {
        if ($pendaftar->is_luar_kota) {
            return true;
        }

        return $this->nomorService->isLuarKota($pendaftar->asal_kota);
    }
}
