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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PpdbController extends Controller
{
    private PpdbRegistrationNumberService $nomorService;
    private array $ppdbPendaftarColumnCache = [];

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
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min($perPage, 1000));

        $query = PpdbPendaftar::query()
            ->with(['akun'])
            ->when($request->filled('status_verifikasi'), fn ($q) => $q->where('status_verifikasi', $request->status_verifikasi))
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', $request->jenjang))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_calon', 'like', "%{$keyword}%")
                        ->orWhere('program_pendaftaran', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk_generated', 'like', "%{$keyword}%")
                        ->orWhere('nik_calon_santri', 'like', "%{$keyword}%")
                        ->orWhere('asal_kota', 'like', "%{$keyword}%")
                        ->orWhere('nomor_umi', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_pendaftaran');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Rekap pendaftar PPDB yang diterima.
     */
    public function rekapDiterima(Request $request): JsonResponse
    {
        $query = PpdbPendaftar::query()
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', $request->query('jenjang')))
            ->when($request->filled('tahun_masuk'), function ($q) use ($request) {
                $tahunMasuk = (int) $request->query('tahun_masuk');
                if ($tahunMasuk > 0) {
                    $q->whereYear('tanggal_daftar', $tahunMasuk);
                }
            });

        $this->applyAcceptedStatusFilter($query);

        $rows = $query
            ->orderByDesc('id_pendaftaran')
            ->get();

        $data = $rows->map(function (PpdbPendaftar $row) {
            return [
                'id_pendaftaran' => $row->id_pendaftaran,
                'waktu_pendaftaran' => optional($row->waktu_pendaftaran)->format('Y-m-d H:i:s')
                    ?? optional($row->created_at)->format('Y-m-d H:i:s'),
                'no_pendaftaran' => $row->no_pendaftaran_final ?: $row->no_pendaftaran,
                'nomor_induk' => $row->nomor_induk_generated,
                'nama_anak' => $row->nama_calon,
                'jenjang' => $row->jenjang ?: $row->program_pendaftaran,
                'tempat_lahir' => $row->tempat_lahir,
                'tanggal_lahir' => optional($row->tanggal_lahir)->format('Y-m-d'),
                'nama_ortu' => $row->nama_ayah ?: $row->nama_ibu,
                'alamat' => $row->alamat_lengkap,
                'no_hp_ortu' => $row->no_hp_ibu ?: $row->no_hp_calon,
                'status_verifikasi' => $row->status_verifikasi,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'jumlah_diterima' => $data->count(),
            ],
        ]);
    }

    /**
     * Export data pendaftar PPDB ke CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $statusFilter = mb_strtolower(trim((string) $request->query('status_verifikasi', 'diterima')));

        $query = PpdbPendaftar::query()
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', $request->query('jenjang')))
            ->when($request->filled('tahun_masuk'), function ($q) use ($request) {
                $tahunMasuk = (int) $request->query('tahun_masuk');
                if ($tahunMasuk > 0) {
                    $q->whereYear('tanggal_daftar', $tahunMasuk);
                }
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = (string) $request->query('q');
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_calon', 'like', "%{$keyword}%")
                        ->orWhere('program_pendaftaran', 'like', "%{$keyword}%")
                        ->orWhere('jenjang', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk_generated', 'like', "%{$keyword}%");
                });
            });

        if ($statusFilter === '' || in_array($statusFilter, ['diterima', 'accepted', 'lulus'], true)) {
            $this->applyAcceptedStatusFilter($query);
            $statusLabel = 'diterima';
        } else {
            $query->whereRaw('LOWER(status_verifikasi) = ?', [$statusFilter]);
            $statusLabel = preg_replace('/[^a-z0-9_-]+/i', '-', $statusFilter) ?: 'filtered';
        }

        $headers = [
            'id_pendaftaran',
            'waktu_pendaftaran',
            'no_pendaftaran',
            'no_pendaftaran_final',
            'nomor_induk_generated',
            'nama_calon',
            'program_pendaftaran',
            'jenjang',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'nik_calon_santri',
            'alamat_lengkap',
            'nama_ayah',
            'no_hp_calon',
            'nama_ibu',
            'no_hp_ibu',
            'status_verifikasi',
            'tanggal_daftar',
            'tanggal_pengumuman',
            'tanggal_diterima',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->orderByDesc('id_pendaftaran')->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->id_pendaftaran,
                        optional($row->waktu_pendaftaran)->format('Y-m-d H:i:s')
                            ?? optional($row->created_at)->format('Y-m-d H:i:s'),
                        $row->no_pendaftaran,
                        $row->no_pendaftaran_final,
                        $row->nomor_induk_generated,
                        $row->nama_calon,
                        $row->program_pendaftaran,
                        $row->jenjang,
                        $row->jenis_kelamin,
                        $row->tempat_lahir,
                        optional($row->tanggal_lahir)->format('Y-m-d'),
                        $row->nik_calon_santri,
                        $row->alamat_lengkap,
                        $row->nama_ayah,
                        $row->no_hp_calon,
                        $row->nama_ibu,
                        $row->no_hp_ibu,
                        $row->status_verifikasi,
                        optional($row->tanggal_daftar)->format('Y-m-d'),
                        optional($row->tanggal_pengumuman)->format('Y-m-d'),
                        optional($row->tanggal_diterima)->format('Y-m-d'),
                    ]);
                }
            });

            fclose($output);
        }, 'ppdb-' . $statusLabel . '-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Simpan data pendaftar PPDB baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_akun' => ['nullable', 'integer', 'exists:akun_pendaftar,id_akun'],
            'nama_calon' => ['required', 'string', 'max:200'],
            'program_pendaftaran' => ['nullable', 'string', 'max:100'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'nik_calon_santri' => ['nullable', 'string', 'max:30'],
            'alamat_lengkap' => ['nullable', 'string'],
            'riwayat_penyakit' => ['nullable', 'string'],
            'nama_ayah' => ['nullable', 'string', 'max:200'],
            'penghasilan_ayah' => ['nullable', 'string', 'max:100'],
            'no_hp_calon' => ['nullable', 'string', 'max:30'],
            'nama_ibu' => ['nullable', 'string', 'max:200'],
            'no_hp_ibu' => ['nullable', 'string', 'max:30'],
            'soal_jawab' => ['nullable', 'string'],
            'file_akta_path' => ['nullable', 'string'],
            'file_kk_path' => ['nullable', 'string'],
            'file_surat_rekomendasi_path' => ['nullable', 'string'],
            'surat_pernyataan_setuju' => ['nullable', 'boolean'],
            'surat_pernyataan_file_path' => ['nullable', 'string'],
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
        $validated['waktu_pendaftaran'] = $tanggalDaftar;

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
    public function show(string $id): JsonResponse
    {
        $data = $this->resolvePendaftarByIdentifier($id, ['akun', 'santriDiterima', 'berkas', 'tes', 'verifikasi', 'notifikasi']);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data pendaftar PPDB.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id);

        $validated = $request->validate([
            'id_akun' => ['nullable', 'integer', 'exists:akun_pendaftar,id_akun'],
            'no_pendaftaran' => ['sometimes', 'string', 'max:50', 'unique:ppdb_pendaftar,no_pendaftaran,' . $pendaftar->id_pendaftaran . ',id_pendaftaran'],
            'no_pendaftaran_final' => ['nullable', 'string', 'max:50', 'unique:ppdb_pendaftar,no_pendaftaran_final,' . $pendaftar->id_pendaftaran . ',id_pendaftaran'],
            'nama_calon' => ['sometimes', 'string', 'max:200'],
            'program_pendaftaran' => ['nullable', 'string', 'max:100'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'nik_calon_santri' => ['nullable', 'string', 'max:30'],
            'alamat_lengkap' => ['nullable', 'string'],
            'riwayat_penyakit' => ['nullable', 'string'],
            'nama_ayah' => ['nullable', 'string', 'max:200'],
            'penghasilan_ayah' => ['nullable', 'string', 'max:100'],
            'no_hp_calon' => ['nullable', 'string', 'max:30'],
            'nama_ibu' => ['nullable', 'string', 'max:200'],
            'no_hp_ibu' => ['nullable', 'string', 'max:30'],
            'soal_jawab' => ['nullable', 'string'],
            'file_akta_path' => ['nullable', 'string'],
            'file_kk_path' => ['nullable', 'string'],
            'file_surat_rekomendasi_path' => ['nullable', 'string'],
            'surat_pernyataan_setuju' => ['nullable', 'boolean'],
            'surat_pernyataan_file_path' => ['nullable', 'string'],
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
    public function destroy(string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id);

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
    public function storeBerkas(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id);

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
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
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
    public function upsertTes(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id);

        $validated = $request->validate([
            'nilai' => ['nullable', 'numeric'],
            'status_tes' => ['nullable', 'string', 'max:30'],
            'metode_tes' => ['nullable', 'string', 'max:50'],
            'soal_tes' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        $tes = PpdbTes::updateOrCreate(
            ['id_pendaftaran' => $pendaftar->id_pendaftaran],
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
    public function upsertVerifikasi(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id, ['akun']);
        $idPendaftaran = (int) $pendaftar->id_pendaftaran;

        $hasilInput = mb_strtolower((string) $request->input('hasil', ''));

        $validated = $request->validate([
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tanggal_verif' => ['nullable', 'date'],
            'hasil' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'kode_kelas_diterima' => [
                Rule::requiredIf(fn () => $this->isStatusDiterima($hasilInput) && $request->boolean('integrasikan_langsung_ke_santri')),
                'nullable',
                'string',
                'exists:data_kelas,kode_kelas',
            ],
            'integrasikan_langsung_ke_santri' => ['nullable', 'boolean'],
            'auto_buat_akun_santri' => ['nullable', 'boolean'],
        ]);

        $validated['tanggal_verif'] = isset($validated['tanggal_verif'])
            ? Carbon::parse($validated['tanggal_verif'])
            : now();

        $integrasiLangsung = $validated['integrasikan_langsung_ke_santri'] ?? false;
        $autoBuatAkunSantri = $validated['auto_buat_akun_santri'] ?? true;

        unset($validated['integrasikan_langsung_ke_santri']);
        unset($validated['auto_buat_akun_santri']);

        $payloadVerifikasi = $validated;
        unset($payloadVerifikasi['kode_kelas_diterima']);

        $integrasiDiterima = null;

        $verifikasi = DB::transaction(function () use ($idPendaftaran, $validated, $payloadVerifikasi, $pendaftar, $integrasiLangsung, $autoBuatAkunSantri, &$integrasiDiterima) {
            $verifikasi = PpdbVerifikasi::updateOrCreate(
                ['id_pendaftaran' => $idPendaftaran],
                $payloadVerifikasi
            );

            if (!empty($validated['hasil'])) {
                $payloadPendaftar = [
                    'status_verifikasi' => $validated['hasil'],
                ];

                if (empty($pendaftar->tanggal_pengumuman) && $this->hasPendaftarColumn('tanggal_pengumuman')) {
                    $payloadPendaftar['tanggal_pengumuman'] = Carbon::parse($validated['tanggal_verif'])->toDateString();
                }

                $payloadPendaftar = $this->filterPendaftarPayloadByExistingColumns($payloadPendaftar);

                if ($payloadPendaftar !== []) {
                    $pendaftar->update($payloadPendaftar);
                }
            }

            if ($this->isStatusDiterima($validated['hasil'] ?? null) && $integrasiLangsung) {
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
    public function storeNotifikasi(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id, ['akun']);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:20'],
            'konten' => ['required', 'string'],
            'sent_at' => ['nullable', 'date'],
            'status_kirim' => ['nullable', 'string', 'max:20'],
            'kirim_email' => ['nullable', 'boolean'],
        ]);

        $notifikasi = PpdbNotifikasi::create([
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
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

        $payloadPendaftar = $this->filterPendaftarPayloadByExistingColumns([
            'id_santri' => $santri->id_santri,
            'nomor_induk_generated' => $nomorInduk,
            'kode_kelas_diterima' => $kodeKelasDiterima,
            'tanggal_diterima' => now()->toDateString(),
            'status_verifikasi' => 'diterima',
        ]);

        if ($payloadPendaftar !== []) {
            $pendaftar->update($payloadPendaftar);
        }

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

    private function applyAcceptedStatusFilter(Builder $query): void
    {
        $acceptedStatuses = ['diterima', 'lulus', 'accepted'];

        $query->where(function (Builder $statusQuery) use ($acceptedStatuses) {
            foreach ($acceptedStatuses as $status) {
                $statusQuery->orWhereRaw('LOWER(status_verifikasi) = ?', [$status]);
            }
        });
    }

    private function resolvePendaftarByIdentifier(string $identifier, array $with = []): PpdbPendaftar
    {
        $normalizedIdentifier = trim($identifier);

        return PpdbPendaftar::query()
            ->with($with)
            ->where(function ($query) use ($normalizedIdentifier) {
                if (ctype_digit($normalizedIdentifier)) {
                    $query->orWhere('id_pendaftaran', (int) $normalizedIdentifier);
                }

                $query
                    ->orWhere('no_pendaftaran', $normalizedIdentifier)
                    ->orWhere('no_pendaftaran_final', $normalizedIdentifier);
            })
            ->firstOrFail();
    }

    private function hasPendaftarColumn(string $column): bool
    {
        if (!array_key_exists($column, $this->ppdbPendaftarColumnCache)) {
            $this->ppdbPendaftarColumnCache[$column] = Schema::hasColumn('ppdb_pendaftar', $column);
        }

        return $this->ppdbPendaftarColumnCache[$column];
    }

    private function filterPendaftarPayloadByExistingColumns(array $payload): array
    {
        return array_filter(
            $payload,
            fn ($column) => $this->hasPendaftarColumn((string) $column),
            ARRAY_FILTER_USE_KEY
        );
    }
}
