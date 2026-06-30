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
use App\Models\PembayaranSpp;
use App\Models\SppSetting;
use App\Support\PpdbRegistrationNumberService;
use App\Support\SppBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PpdbController extends Controller
{
    private PpdbRegistrationNumberService $nomorService;
    private SppBillingService $billingService;
    private array $ppdbPendaftarColumnCache = [];

    public function __construct(SppBillingService $billingService)
    {
        $this->middleware('auth:sanctum');
        $this->nomorService = app(PpdbRegistrationNumberService::class);
        $this->billingService = $billingService;
    }

    /**
     * List data pendaftar PPDB.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min($perPage, 1000));

        $query = PpdbPendaftar::query()
            ->with(['akun', 'berkas'])
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
        $query = PpdbPendaftar::query()
            ->when($request->filled('jenjang'), function ($q) use ($request) {
                $jenjang = $request->query('jenjang');
                $q->where(function ($sub) use ($jenjang) {
                    $sub->where('jenjang', $jenjang)
                        ->orWhere('program_pendaftaran', $jenjang);
                });
            })
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

        $statusLabel = 'filtered';
        if ($request->has('status_verifikasi')) {
            $statusRaw = trim((string) $request->query('status_verifikasi'));
            $statusFilter = mb_strtolower($statusRaw);
            
            if ($statusFilter !== 'all' && $statusFilter !== '') {
                if (in_array($statusFilter, ['diterima', 'accepted', 'lulus'], true)) {
                    $this->applyAcceptedStatusFilter($query);
                    $statusLabel = 'diterima';
                } else {
                    $query->whereRaw('LOWER(status_verifikasi) = ?', [$statusFilter]);
                    $statusLabel = preg_replace('/[^a-z0-9_-]+/i', '-', $statusFilter) ?: 'filtered';
                }
            } else {
                $statusLabel = 'semua';
            }
        } else {
            // Default to 'diterima' if the query parameter is not present at all
            $this->applyAcceptedStatusFilter($query);
            $statusLabel = 'diterima';
        }

        $headers = [
            'No.',
            'No. Pendaftaran',
            'Nomor Induk (NIS)',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Alamat Lengkap',
            'Asal Sekolah / Kota',
            'Jenjang',
            'Nama Orang Tua / Wali',
            'No. HP',
            'Status Verifikasi',
            'Tanggal Daftar',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel
            fputs($output, "\xEF\xBB\xBF");
            
            // Force Excel to use semicolon delimiter
            fputs($output, "sep=;\n");
            
            fputcsv($output, $headers, ';');

            $index = 1;
            $query->orderBy('jenjang')
                ->orderBy('nomor_induk_generated')
                ->orderBy('nama_calon')
                ->chunk(500, function ($rows) use ($output, &$index) {
                    foreach ($rows as $row) {
                        fputcsv($output, [
                            $index++,
                            $row->no_pendaftaran_final ?: $row->no_pendaftaran,
                            $row->nomor_induk_generated ?: '-',
                            $row->nama_calon,
                            $row->jenis_kelamin === 'L' ? 'Laki-laki' : ($row->jenis_kelamin === 'P' ? 'Perempuan' : '-'),
                            $row->tempat_lahir ?: '-',
                            optional($row->tanggal_lahir)->format('d-m-Y') ?: '-',
                            $row->alamat_lengkap ?: '-',
                            $row->asal_kota ?: '-',
                            $row->jenjang ?: $row->program_pendaftaran ?: '-',
                            $row->nama_ayah ?: $row->nama_ibu ?: '-',
                            $row->no_hp_ibu ?: $row->no_hp_calon ?: '-',
                            ucfirst($row->status_verifikasi),
                            optional($row->tanggal_daftar)->format('d-m-Y') ?: '-',
                        ], ';');
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
            'is_anak_guru' => ['nullable', 'boolean'],
            'pilihan_uang_gedung' => ['nullable', 'integer', 'in:1,2'],
            'pilihan_infaq_bulanan' => ['nullable', 'integer', 'in:1,2'],
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

        $validated = $request->validate([
            'jenis_berkas' => ['required', 'string', 'max:80'],
            'file_path' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
            'uploaded_at' => ['nullable', 'date'],
        ]);

        $filePath = trim((string) ($validated['file_path'] ?? ''));
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
            $fileName = $safeName . '_' . time() . '.' . $extension;
            $filePath = $file->storeAs('ppdb/berkas', $fileName, 'public');
        }

        if ($filePath === '') {
            return response()->json([
                'message' => 'Upload berkas gagal: kirimkan file atau file_path.',
            ], 422);
        }

        $berkas = PpdbBerkas::updateOrCreate(
            [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'jenis_berkas' => $validated['jenis_berkas'],
            ],
            [
                'file_path' => $filePath,
                'uploaded_at' => isset($validated['uploaded_at'])
                    ? Carbon::parse($validated['uploaded_at'])
                    : now(),
            ]
        );

        if (in_array($validated['jenis_berkas'], ['akta', 'kk', 'surat_rekomendasi', 'surat_pernyataan', 'bukti_ortu_guru'], true)) {
            $fieldMap = [
                'akta' => 'file_akta_path',
                'kk' => 'file_kk_path',
                'surat_rekomendasi' => 'file_surat_rekomendasi_path',
                'surat_pernyataan' => 'surat_pernyataan_file_path',
                'bukti_ortu_guru' => 'bukti_ortu_guru_path',
            ];

            $pendaftar->update([
                $fieldMap[$validated['jenis_berkas']] => $filePath,
            ]);
        }

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

        $hasilInput = $this->normalizeVerifikasiResult(
            $request->input('hasil', $request->input('status_verifikasi', $request->input('status', '')))
        );
        if ($hasilInput !== '') {
            $request->merge(['hasil' => $hasilInput]);
        }

        // In the new flow, there is no registration payment step before acceptance.
        // Therefore, we bypass the check for registration fee payment.
        /*
        if ($this->isStatusDiterima($hasilInput)) {
            if (!$this->isPembayaranPpdbLunas($idPendaftaran)) {
                return response()->json([
                    'message' => 'Pendaftar belum bisa diterima. Pembayaran administrasi PPDB belum lunas atau belum diverifikasi.',
                ], 422);
            }
        }
        */

        $validated = $request->validate([
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tanggal_verif' => ['nullable', 'date'],
            'hasil' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
            'bukti_ortu_guru_verified' => ['nullable', 'boolean'],
            'kode_kelas_diterima' => [
                Rule::requiredIf(fn () => $this->isStatusDiterima($hasilInput)),
                'nullable',
                'string',
                'exists:data_kelas,kode_kelas',
            ],
            'integrasikan_langsung_ke_santri' => ['nullable', 'boolean'],
            'auto_buat_akun_santri' => ['nullable', 'boolean'],
            'buat_tagihan_ppdb' => ['nullable', 'boolean'],
        ]);

        $validated['tanggal_verif'] = isset($validated['tanggal_verif'])
            ? Carbon::parse($validated['tanggal_verif'])
            : now();

        $integrasiLangsung = $validated['integrasikan_langsung_ke_santri'] ?? false;
        $autoBuatAkunSantri = $validated['auto_buat_akun_santri'] ?? true;
        $buatTagihanPpdb = $validated['buat_tagihan_ppdb'] ?? true;

        unset($validated['integrasikan_langsung_ke_santri']);
        unset($validated['auto_buat_akun_santri']);
        unset($validated['buat_tagihan_ppdb']);

        $payloadVerifikasi = $validated;
        unset($payloadVerifikasi['kode_kelas_diterima']);
        unset($payloadVerifikasi['bukti_ortu_guru_verified']);

        $integrasiDiterima = null;
        $tagihanPpdb = null;

        $verifikasi = DB::transaction(function () use ($idPendaftaran, $validated, $payloadVerifikasi, $pendaftar, $integrasiLangsung, $autoBuatAkunSantri, $buatTagihanPpdb, &$integrasiDiterima, &$tagihanPpdb) {
            $verifikasi = PpdbVerifikasi::updateOrCreate(
                ['id_pendaftaran' => $idPendaftaran],
                $payloadVerifikasi
            );

            $payloadPendaftar = [];
            if (!empty($validated['hasil'])) {
                $payloadPendaftar['status_verifikasi'] = $validated['hasil'];

                if (empty($pendaftar->tanggal_pengumuman) && $this->hasPendaftarColumn('tanggal_pengumuman')) {
                    $payloadPendaftar['tanggal_pengumuman'] = Carbon::parse($validated['tanggal_verif'])->toDateString();
                }

                if ($this->isStatusDiterima($validated['hasil'])) {
                    $tanggalDiterima = Carbon::parse($validated['tanggal_verif'] ?? now());
                    $payloadPendaftar['tanggal_diterima'] = $tanggalDiterima->toDateString();
                    $payloadPendaftar['batas_bayar_uang_pangkal'] = $tanggalDiterima->copy()->addMonths(2)->toDateString();
                    $payloadPendaftar['batas_bayar_spp'] = $tanggalDiterima->copy()->addMonth()->toDateString();
                    $payloadPendaftar['status_uang_pangkal'] = $pendaftar->status_uang_pangkal ?: 'menunggu';
                    $payloadPendaftar['status_spp'] = $pendaftar->status_spp ?: 'menunggu';
                }

                if (!empty($validated['kode_kelas_diterima'])) {
                    $payloadPendaftar['kode_kelas_diterima'] = $validated['kode_kelas_diterima'];
                }
            }

            if (array_key_exists('bukti_ortu_guru_verified', $validated)) {
                $payloadPendaftar['bukti_ortu_guru_verified'] = (bool) $validated['bukti_ortu_guru_verified'];
            }

            if ($payloadPendaftar !== []) {
                $payloadPendaftarFiltered = $this->filterPendaftarPayloadByExistingColumns($payloadPendaftar);
                if (array_key_exists('bukti_ortu_guru_verified', $payloadPendaftar)) {
                    $payloadPendaftarFiltered['bukti_ortu_guru_verified'] = $payloadPendaftar['bukti_ortu_guru_verified'];
                }
                if ($payloadPendaftarFiltered !== []) {
                    $pendaftar->update($payloadPendaftarFiltered);
                }
            }

            if ($this->isStatusDiterima($validated['hasil'] ?? null) && $integrasiLangsung) {
                $integrasiDiterima = $this->integrasikanPendaftarDiterima(
                    $pendaftar,
                    (string) ($validated['kode_kelas_diterima'] ?? ''),
                    $autoBuatAkunSantri
                );
                // Refresh agar id_santri yang baru di-assign oleh integrasikanPendaftarDiterima
                // terlihat oleh createTagihanPpdbIfNeeded dan blok back-fill di bawah.
                $pendaftar->refresh();
            }

            if ($this->isStatusDiterima($validated['hasil'] ?? null) && $buatTagihanPpdb) {
                $tagihanPpdb = $this->createTagihanPpdbIfNeeded($pendaftar);
            }

            // Hubungkan tagihan PPDB lama ke id_santri (jika pendaftar sudah punya id_santri dari integrasi sebelumnya)
            if ($this->isStatusDiterima($validated['hasil'] ?? null) && !empty($pendaftar->id_santri)) {
                PembayaranSpp::where('id_pendaftaran', $pendaftar->id_pendaftaran)
                    ->whereNull('id_santri')
                    ->update(['id_santri' => $pendaftar->id_santri]);
            }

            return $verifikasi;
        });

        return response()->json([
            'message' => 'Verifikasi manual PPDB berhasil disimpan.',
            'data' => $verifikasi,
            'integrasi_diterima' => $integrasiDiterima,
            'tagihan_ppdb' => $tagihanPpdb,
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

    /**
     * Buat tagihan pembayaran PPDB untuk pendaftar yang diterima.
     * Bisa dipanggil dari halaman PPDB admin setelah pendaftar diterima.
     */
    public function createTagihanPpdb(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id, ['akun']);

        if (!$this->isStatusDiterima($pendaftar->status_verifikasi)) {
            return response()->json([
                'message' => 'Tagihan PPDB hanya dapat dibuat untuk pendaftar yang sudah diterima.',
            ], 422);
        }

        $tagihanExisting = PembayaranSpp::where('id_pendaftaran', $pendaftar->id_pendaftaran)->first();
        if ($tagihanExisting) {
            return response()->json([
                'message' => 'Tagihan PPDB untuk pendaftar ini sudah ada.',
                'data'    => $tagihanExisting->load(['setting', 'kwitansi']),
            ], 422);
        }

        $validated = $request->validate([
            'id_setting'    => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'nominal_bayar' => ['nullable', 'numeric', 'min:0'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar'  => ['nullable', 'string', 'max:50'],
        ]);

        $tagihan = $this->createTagihanPpdbIfNeeded($pendaftar, $validated);

        return response()->json([
            'message' => 'Tagihan PPDB berhasil dibuat.',
            'data'    => $tagihan->load(['setting', 'pendaftarPpdb', 'kwitansi']),
        ], 201);
    }

    /**
     * Buat tagihan infaq PPDB untuk pendaftar yang sudah diterima.
     */
    public function createTagihanInfaq(Request $request, string $id): JsonResponse
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id, ['akun', 'santriDiterima']);

        if (!$this->isStatusDiterima($pendaftar->status_verifikasi)) {
            return response()->json([
                'message' => 'Tagihan infaq hanya dapat dibuat untuk pendaftar yang sudah diterima.',
            ], 422);
        }

        $santri = $pendaftar->santriDiterima ?: ($pendaftar->id_santri ? DataSantri::find($pendaftar->id_santri) : null);
        $tagihan = $this->createTagihanInfaqIfNeeded($pendaftar, $santri);

        if (!$tagihan) {
            return response()->json([
                'message' => 'Tagihan infaq belum dapat dibuat. Pastikan pilihan infaq sudah diisi.',
            ], 422);
        }

        return response()->json([
            'message' => 'Tagihan infaq berhasil dibuat.',
            'data'    => $tagihan->load(['setting', 'pendaftarPpdb', 'kwitansi']),
        ], 201);
    }

    private function createTagihanPpdbIfNeeded(PpdbPendaftar $pendaftar, array $overrides = []): ?PembayaranSpp
    {
        $rawJenjang = strtoupper(trim((string) ($pendaftar->jenjang ?? $pendaftar->program_pendaftaran ?? '')));
        $jenjang = match ($rawJenjang) {
            'MI', 'SD' => 'PRATAHFIDZ',
            'SMP', 'MTS' => 'MTS',
            'SMA', 'MA' => 'MA',
            default => $rawJenjang,
        };
        
        $configs = [
            'PAUD' => [
                'uang_pangkal_a' => 500000,
                'uang_pangkal_b' => 500000,
                'perlengkapan' => 300000,
                'uang_modul' => 0,
                'infaq_bulanan_a' => 200000,
                'infaq_bulanan_b' => 250000,
            ],
            'TK' => [
                'uang_pangkal_a' => 1000000,
                'uang_pangkal_b' => 1500000,
                'perlengkapan' => 1200000,
                'uang_modul' => 0,
                'infaq_bulanan_a' => 300000,
                'infaq_bulanan_b' => 350000,
            ],
            'PRATAHFIDZ' => [
                'uang_pangkal_a' => 1800000,
                'uang_pangkal_b' => 2000000,
                'perlengkapan' => 0,
                'uang_modul' => 200000,
                'infaq_bulanan_a' => 350000,
                'infaq_bulanan_b' => 400000,
            ],
            'MTS' => [
                'uang_pangkal_a' => 1500000,
                'uang_pangkal_b' => 2000000,
                'perlengkapan' => 875000, ## diperjelas saja biayanya apa saja 
                'uang_modul' => 250000,
                'infaq_bulanan_a' => 600000, 
                'infaq_bulanan_b' => 650000,
            ],
            'MA' => [
                'uang_pangkal_a' => 1500000,
                'uang_pangkal_b' => 2000000,
                'perlengkapan' => 875000, 
                'uang_modul' => 250000,
                'infaq_bulanan_a' => 650000,
                'infaq_bulanan_b' => 700000,
            ],
            'MTQU' => [
                'uang_pangkal_a' => 1800000,
                'uang_pangkal_b' => 2000000,
                'perlengkapan' => 0, ##opsionall ditambahi biaya meja belajar dll
                'uang_modul' => 200000,
                'infaq_bulanan_a' => 650000,
                'infaq_bulanan_b' => 700000,
            ],
        ];

        $configInfaq = $configs[$jenjang] ?? null;

        // If choices are present, use the advanced selection logic
        if ($configInfaq && $pendaftar->pilihan_uang_gedung && $pendaftar->pilihan_infaq_bulanan) {
            // Nominal yang dipilih pendaftar
            $nominalUangPangkal = $pendaftar->pilihan_uang_gedung == 1 
                ? $configInfaq['uang_pangkal_a'] 
                : $configInfaq['uang_pangkal_b'];
            
            $nominalInfaqBulanan = $pendaftar->pilihan_infaq_bulanan == 1
                ? $configInfaq['infaq_bulanan_a']
                : $configInfaq['infaq_bulanan_b'];
            
            // Apply diskon anak guru HANYA untuk Uang Pangkal
            if ($pendaftar->is_anak_guru) {
                $nominalUangPangkal = $nominalUangPangkal * 0.5; // 50% diskon
            }
            
            // Gabungkan Uang Pangkal + Perlengkapan + SPP Bulanan Pertama menjadi SATU tagihan
            $totalTagihanGabungan = $nominalUangPangkal + $configInfaq['perlengkapan'] + $nominalInfaqBulanan;
            
            // Create/Update the combined tagihan
            $settingGabungan = SppSetting::firstOrCreate(
                [
                    'jenjang' => $pendaftar->jenjang,
                    'keterangan' => 'Uang Gedung + Perlengkapan + SPP Pertama',
                    'periode' => optional($pendaftar->period)->nama_periode ?? now()->format('Y'),
                ],
                [
                    'nominal' => $totalTagihanGabungan,
                    'aktif' => true,
                ]
            );

            $tagihanGabungan = PembayaranSpp::firstOrCreate(
                [
                    'id_pendaftaran' => $pendaftar->id_pendaftaran,
                    'id_setting' => $settingGabungan->id_setting,
                ],
                [
                    'id_santri' => $pendaftar->id_santri ?? null,
                    'nominal_bayar' => $totalTagihanGabungan,
                    'status' => 'menunggu_pembayaran',
                    'jenis_tagihan' => 'ppdb',
                ]
            );

            // Buat tagihan Uang Modul terpisah (jika ada)
            if ($configInfaq['uang_modul'] > 0) {
                $settingModul = SppSetting::firstOrCreate(
                    [
                        'jenjang' => $pendaftar->jenjang,
                        'keterangan' => 'Uang Modul Semester Ganjil',
                        'periode' => optional($pendaftar->period)->nama_periode ?? now()->format('Y'),
                    ],
                    [
                        'nominal' => $configInfaq['uang_modul'],
                        'aktif' => true,
                    ]
                );

                PembayaranSpp::firstOrCreate(
                    [
                        'id_pendaftaran' => $pendaftar->id_pendaftaran,
                        'id_setting' => $settingModul->id_setting,
                    ],
                    [
                        'id_santri' => $pendaftar->id_santri ?? null,
                        'nominal_bayar' => $configInfaq['uang_modul'],
                        'status' => 'menunggu_pembayaran',
                        'jenis_tagihan' => 'ppdb',
                    ]
                );
            }

            return $tagihanGabungan;
        }

        // FALLBACK: Old generic tagihan logic
        $existing = PembayaranSpp::where('id_pendaftaran', $pendaftar->id_pendaftaran)->first();
        if ($existing) {
            return $existing;
        }

        $setting = null;
        if (!empty($overrides['id_setting'])) {
            $setting = SppSetting::find($overrides['id_setting']);
        } elseif ($jenjang) {
            $setting = SppSetting::whereNull('id_santri')
                ->whereRaw('UPPER(jenjang) = ?', [$jenjang])
                ->orderByDesc('id_setting')
                ->first();
        }

        $nominal = $overrides['nominal_bayar'] ?? ($setting?->jumlah ?? null);

        return PembayaranSpp::create([
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'id_santri' => $pendaftar->id_santri ?? null,
            'id_setting' => $setting?->id_setting ?? ($overrides['id_setting'] ?? null),
            'nominal_bayar' => $nominal,
            'tanggal_bayar' => $overrides['tanggal_bayar'] ?? null,
            'metode_bayar' => $overrides['metode_bayar'] ?? null,
            'status' => 'menunggu_pembayaran',
            'jenis_tagihan' => 'ppdb',
        ]);
    }

    /**
     * Buat tagihan infaq bulanan untuk santri yang diterima via PPDB,
     * apabila pendaftar memilih nominal infaq bulanan.
     *
     * Metode ini bersifat idempotent: jika tagihan infaq sudah ada
     * untuk kombinasi (id_santri, id_pendaftaran, bulan=null, id_setting),
     * record yang sudah ada akan dikembalikan tanpa membuat duplikat.
     */
    private function createTagihanInfaqIfNeeded(PpdbPendaftar $pendaftar, ?DataSantri $santri = null): ?PembayaranSpp
    {
        // 1. Periksa apakah pilihan_infaq_bulanan diisi (tidak null dan > 0)
        $nominalInfaq = $pendaftar->pilihan_infaq_bulanan;
        if (empty($nominalInfaq) || $nominalInfaq <= 0) {
            return null;
        }

        // 2. Cari SppSetting yang cocok untuk kategori infaq
        $setting = null;

        // Dapatkan kelas santri untuk pencocokan jenjang / unit bila sudah terintegrasi
        $kelas = $santri?->kelas;

        $query = SppSetting::query()
            ->whereNull('id_santri') // Hanya setting global, bukan per-santri
            ->where(function ($q) {
                // Filter berdasarkan keterangan atau nama kategori tagihan yang mengandung "infaq"
                $q->whereRaw('LOWER(keterangan) LIKE ?', ['%infaq%'])
                  ->orWhereHas('kategoriTagihan', function ($kq) {
                      $kq->whereRaw('LOWER(nama_tagihan) LIKE ?', ['%infaq%']);
                  });
            });

        // Filter aktif jika kolom tersedia
        if (Schema::hasColumn('spp_setting', 'aktif')) {
            $query->where('aktif', true);
        }

        // Cocokkan dengan jenjang or unit santri
        if ($kelas) {
            $query->where(function ($q) use ($kelas) {
                if (!empty($kelas->kode_unit)) {
                    // id_unit pada spp_setting mengacu ke data_unit; kelas memiliki kode_unit
                    // Lakukan sub-query untuk mendapatkan id_unit yang sesuai kode_unit
                    $q->whereHas('unit', function ($uq) use ($kelas) {
                        $uq->where('kode_unit', $kelas->kode_unit);
                    });
                }

                // Juga coba cocok berdasarkan kode_kelas langsung jika kolom tersedia
                if (Schema::hasColumn('spp_setting', 'kode_kelas')) {
                    $q->orWhere('kode_kelas', $kelas->kode_kelas);
                }
            });
        } elseif (!empty($pendaftar->jenjang) || !empty($pendaftar->program_pendaftaran)) {
            $jenjang = strtoupper(trim((string) ($pendaftar->jenjang ?: $pendaftar->program_pendaftaran)));
            $query->where(function ($q) use ($jenjang) {
                $q->whereRaw('UPPER(COALESCE(jenjang, "")) = ?', [$jenjang]);

                if (Schema::hasColumn('spp_setting', 'kode_kelas')) {
                    $q->orWhereRaw('UPPER(COALESCE(kode_kelas, "")) = ?', [$jenjang]);
                }
            });
        }

        $setting = $query->orderByDesc('id_setting')->first();

        $rawJenjang = strtoupper(trim((string) ($pendaftar->jenjang ?: $pendaftar->program_pendaftaran ?: '')));
        $jenjang = match ($rawJenjang) {
            'MI', 'SD' => 'PRATAHFIDZ',
            'SMP', 'MTS' => 'MTS',
            'SMA', 'MA' => 'MA',
            default => $rawJenjang,
        };

        $configs = [
            'PAUD' => [
                'infaq_bulanan_a' => 200000,
                'infaq_bulanan_b' => 250000,
            ],
            'TK' => [
                'infaq_bulanan_a' => 300000,
                'infaq_bulanan_b' => 350000,
            ],
            'PRATAHFIDZ' => [
                'infaq_bulanan_a' => 350000,
                'infaq_bulanan_b' => 400000,
            ],
            'MTS' => [
                'infaq_bulanan_a' => 600000,
                'infaq_bulanan_b' => 650000,
            ],
            'MA' => [
                'infaq_bulanan_a' => 650000,
                'infaq_bulanan_b' => 700000,
            ],
            'MTQU' => [
                'infaq_bulanan_a' => 650000,
                'infaq_bulanan_b' => 700000,
            ],
        ];

        $config = $configs[$jenjang] ?? $configs['MTS'];
        $nominalInfaqVal = $nominalInfaq == 1 ? $config['infaq_bulanan_a'] : $config['infaq_bulanan_b'];

        // 3. Buat atau dapatkan tagihan infaq menggunakan firstOrCreate untuk idempotency
        $tagihan = PembayaranSpp::firstOrCreate(
            [
                'id_santri'       => $santri->id_santri,
                'id_pendaftaran'  => $pendaftar->id_pendaftaran,
                'bulan'           => null,
                'id_setting'      => $setting?->id_setting,
                'jenis_tagihan'   => 'infaq',
            ],
            [
                // 4. Nilai tagihan: gunakan pilihan_infaq_bulanan yang sudah di-resolve ke nominal sebenarnya
                'nominal_bayar' => $nominalInfaqVal,
                'status'        => 'menunggu_pembayaran',
            ]
        );

        // 5. Log hasil pembuatan tagihan
        Log::info('Tagihan infaq bulanan PPDB', [
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'id_santri'      => $santri->id_santri,
            'id_setting'     => $setting?->id_setting,
            'nominal_infaq'  => $nominalInfaq,
            'is_created'     => $tagihan->wasRecentlyCreated,
        ]);

        return $tagihan;
    }

    private function isPembayaranPpdbLunas(int $idPendaftaran): bool
    {
        $lunasStatuses = [
            'lunas',
            'paid',
            'terverifikasi',
            'verified',
            'selesai',
        ];

        return PembayaranSpp::query()
            ->where('id_pendaftaran', $idPendaftaran)
            ->whereIn(DB::raw('LOWER(status)'), $lunasStatuses)
            ->exists();
    }

    private function isStatusDiterima(?string $hasil): bool
    {
        $normalized = $this->normalizeVerifikasiResult($hasil);

        return in_array($normalized, ['diterima', 'lulus', 'accepted'], true);
    }

    private function normalizeVerifikasiResult(?string $hasil): string
    {
        $normalized = mb_strtolower(trim((string) $hasil));

        return match ($normalized) {
            'accepted', 'approve', 'approved', 'lulus' => 'diterima',
            'rejected', 'reject' => 'ditolak',
            'waiting', 'wait', 'pending', 'menunggu' => 'pending',
            default => $normalized,
        };
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
            'jenis_kelamin' => $pendaftar->jenis_kelamin,
            'tempat_lahir' => $pendaftar->tempat_lahir,
            'tanggal_lahir' => $pendaftar->tanggal_lahir,
            'alamat_tinggal' => $pendaftar->alamat_lengkap,
            'nama_ayah_kandung' => $pendaftar->nama_ayah,
            'nama_ibu_kandung' => $pendaftar->nama_ibu,
            'nama_wali' => $pendaftar->nomor_umi,
            'is_anak_guru' => (bool) $pendaftar->is_anak_guru,
        ]);

        $santri->save();

        // Bug fix: Hubungkan semua tagihan PPDB (yang dibuat sebelum integrasi) ke id_santri yang baru.
        // Tanpa ini, halaman administrasi santri (query by id_santri) tidak menemukan tagihan PPDB awal.
        // Link existing PPDB payment records to the new santri id,
        // so PPDB bills and SPP bills are merged under the same santri identity.
        PembayaranSpp::where('id_pendaftaran', $pendaftar->id_pendaftaran)
            ->whereNull('id_santri')
            ->update(['id_santri' => $santri->id_santri]);

        // Transfer proof of payment from ppdb_pendaftar to pembayaran_spp
        if (!empty($pendaftar->bukti_uang_pangkal_path) || !empty($pendaftar->bukti_spp_path)) {
            $ppdbPayments = PembayaranSpp::where('id_pendaftaran', $pendaftar->id_pendaftaran)
                ->where('id_santri', $santri->id_santri)
                ->get();
            
            foreach ($ppdbPayments as $payment) {
                // Update proof for uang pangkal payment
                if (!empty($pendaftar->bukti_uang_pangkal_path)) {
                    $payment->update(['bukti_bayar_path' => $pendaftar->bukti_uang_pangkal_path]);
                }
                // Update proof for SPP payment if available
                elseif (!empty($pendaftar->bukti_spp_path)) {
                    $payment->update(['bukti_bayar_path' => $pendaftar->bukti_spp_path]);
                }
            }
        }

        // Provision SPP billing for the active santri
        $this->billingService->provisionBillingForActiveSantri($santri);

        $this->createTagihanInfaqIfNeeded($pendaftar, $santri);

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

    public function verifikasiUangPangkal(Request $request, $id)
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id);
        
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:menunggu,menunggu_verifikasi,dp,lunas,gagal'],
        ]);
        
        $pendaftar->update([
            'status_uang_pangkal' => $validated['status']
        ]);
        
        return response()->json([
            'message' => 'Status pembayaran Uang Pangkal berhasil diperbarui',
            'data' => [
                'status_uang_pangkal' => $pendaftar->status_uang_pangkal
            ]
        ]);
    }

    public function verifikasiSpp(Request $request, $id)
    {
        $pendaftar = $this->resolvePendaftarByIdentifier($id);
        
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:menunggu,menunggu_verifikasi,lunas,gagal'],
        ]);
        
        $pendaftar->update([
            'status_spp' => $validated['status']
        ]);
        
        return response()->json([
            'message' => 'Status pembayaran SPP berhasil diperbarui',
            'data' => [
                'status_spp' => $pendaftar->status_spp
            ]
        ]);
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

    /**
     * Endpoint to list available classes for PPDB admission.
     * POIN 2: Hanya menampilkan kelas tingkat 1 untuk santri baru
     * (MI/MTs/MA → hanya kelas 1, PAUD/TK → semua kelas).
     */
    public function availableKelas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jenjang' => ['required', 'string'],
            'tahun_ajaran' => ['nullable', 'string'],
        ]);

        $jenjang = strtoupper($validated['jenjang']);
        // Normalize jenjang to unit code (MI, MTS, MA)
        $unitCode = match ($jenjang) {
            'MI', 'SD' => 'MI',
            'MTS', 'SMP' => 'MTS',
            'MA', 'SMA' => 'MA',
            default => $jenjang,
        };

        $tahunAjaran = $validated['tahun_ajaran'] ?? null;
        if (!$tahunAjaran) {
            $activeTA = \App\Models\DataTahunAjaran::where('status', 'AKTIF')->first();
            $tahunAjaran = $activeTA ? $activeTA->kode_tahun : null;
        }

        $query = DataKelas::query()
            ->withCount(['santri as jumlah_santri_aktif' => function ($q) {
                $q->where('status', 'AKTIF')->where('is_deleted', false);
            }])
            ->where('status', 'AKTIF')
            ->where('is_deleted', false)
            ->where('kode_unit', $unitCode);

        if ($tahunAjaran) {
            $query->where('tahun_ajaran', $tahunAjaran);
        }

        // POIN 2: Filter hanya kelas 1 (tingkat penerimaan pertama) untuk
        // jenjang MI, MTs, MA. Santri baru tidak mungkin langsung masuk kelas 2+.
        // PAUD dan TK tidak difilter karena hanya punya satu tingkat.
        $isJenjangBerjenjang = in_array($unitCode, ['MI', 'MTS', 'MA'], true);
        if ($isJenjangBerjenjang) {
            $query->where(function ($q) {
                // Cek dari nama_kelas atau kode_kelas yang mengandung angka 1 di depan
                $q->where(function ($sub) {
                    // nama_kelas dimulai dengan "1" diikuti spasi/huruf/tanda baca
                    $sub->whereRaw("nama_kelas REGEXP '^1[^0-9]'")
                        ->orWhereRaw("nama_kelas REGEXP '^1$'")
                        ->orWhereRaw("LOWER(nama_kelas) LIKE 'kelas 1 %'")
                        ->orWhereRaw("LOWER(nama_kelas) LIKE 'kelas 1%'");
                })->orWhere(function ($sub) {
                    // kode_kelas dimulai dengan "1"
                    $sub->whereRaw("kode_kelas REGEXP '^1[^0-9]'")
                        ->orWhereRaw("kode_kelas REGEXP '^1$'");
                });
            });
        }

        $classes = $query->get();

        $data = $classes->map(function ($kelas) {
            $kapasitas = 30; // standard capacity
            $sisaKuota = max(0, $kapasitas - $kelas->jumlah_santri_aktif);

            return [
                'id_kelas' => $kelas->id_kelas,
                'kode_kelas' => $kelas->kode_kelas,
                'nama_kelas' => $kelas->nama_kelas,
                'nama_jurusan' => $kelas->nama_jurusan,
                'kode_unit' => $kelas->kode_unit,
                'tahun_ajaran' => $kelas->tahun_ajaran,
                'jumlah_santri_aktif' => $kelas->jumlah_santri_aktif,
                'kapasitas' => $kapasitas,
                'sisa_kuota' => $sisaKuota,
                'is_penuh' => $sisaKuota <= 0,
            ];
        });

        return response()->json([
            'message' => 'Daftar kelas tersedia berhasil diambil.',
            'data' => $data,
        ]);
    }
}
