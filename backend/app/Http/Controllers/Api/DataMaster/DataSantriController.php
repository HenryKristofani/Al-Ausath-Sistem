<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
use App\Models\DataTahunAjaran;
use App\Models\DataSantri;
use App\Models\PembayaranSpp;
use App\Models\SppSetting;
use App\Support\SppBillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataSantriController extends Controller
{
    private SppBillingService $billingService;

    public function __construct(SppBillingService $billingService)
    {
        $this->middleware('auth:sanctum');
        $this->billingService = $billingService;
    }

    /**
     * List data santri.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataSantri::query()
            ->with(['kelas', 'akun'])
            ->where('is_deleted', false)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('kode_kelas'), fn($q) => $q->where('kode_kelas', $request->kode_kelas))
            ->when($request->filled('kode_unit') || $request->filled('tahun_ajaran'), function ($q) use ($request) {
                $q->whereHas('kelas', function ($kelasQuery) use ($request) {
                    $kelasQuery
                        ->when($request->filled('kode_unit'), fn($subQuery) => $subQuery->where('kode_unit', $request->kode_unit))
                        ->when($request->filled('tahun_ajaran'), fn($subQuery) => $subQuery->where('tahun_ajaran', $request->tahun_ajaran));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_santri');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Opsi ringan data santri untuk autocomplete.
     */
    public function options(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $limit = max(1, min((int) $request->query('limit', 20), 50));

        $options = DataSantri::query()
            ->leftJoin('data_kelas', 'data_kelas.kode_kelas', '=', 'data_santri.kode_kelas')
            ->leftJoin('data_unit', 'data_unit.kode_unit', '=', 'data_kelas.kode_unit')
            ->where('data_santri.is_deleted', false)
            ->select([
                'data_santri.id_santri',
                'data_santri.nomor_induk',
                'data_santri.nama_lengkap_santri',
                'data_santri.kode_kelas',
                'data_kelas.kode_unit',
                'data_unit.nama_unit',
            ])
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('data_santri.nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('data_santri.nama_lengkap_santri', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('data_santri.nama_lengkap_santri')
            ->limit($limit)
            ->get();

        return response()->json($options);
    }

    /**
     * Simpan data santri baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => [
                'required',
                'string',
                'max:20',
                Rule::unique('data_santri', 'nomor_induk')->where(fn($q) => $q->where('is_deleted', false)),
            ],
            'nama_lengkap_santri' => ['required', 'string', 'max:200'],
            'kode_kelas' => [
                'required',
                'string',
                'max:10',
                Rule::exists('data_kelas', 'kode_kelas')->where(fn($q) => $q->where('is_deleted', false)),
            ],
            'status' => ['nullable', 'string', 'max:20'],
            'tahun_masuk' => ['nullable', 'integer'],
            'tahun_lulus' => ['nullable', 'integer'],
            'jenis_kelamin' => ['nullable', 'string', 'size:1'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'berat_badan' => ['nullable', 'numeric'],
            'tinggi_badan' => ['nullable', 'numeric'],
            'gol_darah' => ['nullable', 'string', 'max:5'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kota_kabupaten' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'alamat_tinggal' => ['nullable', 'string'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_email' => ['nullable', 'email', 'max:100'],
            'nama_ayah_kandung' => ['nullable', 'string', 'max:200'],
            'nama_ibu_kandung' => ['nullable', 'string', 'max:200'],
            'nama_wali' => ['nullable', 'string', 'max:200'],
        ]);

        $data = DB::transaction(function () use ($validated) {
            $existingDeleted = DataSantri::where('nomor_induk', $validated['nomor_induk'])
                ->where('is_deleted', true)
                ->first();

            if ($existingDeleted) {
                $existingDeleted->update(array_merge($validated, [
                    'is_deleted' => false,
                    'deleted_at' => null,
                ]));

                $existingDeleted->load(['kelas.unit', 'akun']);
                                $this->billingService->provisionBillingForActiveSantri($existingDeleted);

                return $existingDeleted;
            }

            $validated['is_deleted'] = false;
            $validated['deleted_at'] = null;
            $data = DataSantri::create($validated);
            $data->load(['kelas.unit', 'akun']);
                        $this->billingService->provisionBillingForActiveSantri($data);

            return $data;
        });

        return response()->json([
            'message' => 'Data santri berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data santri.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataSantri::query()
            ->with(['kelas', 'akun'])
            ->where('is_deleted', false)
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data santri.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $santri = DataSantri::where('is_deleted', false)->findOrFail($id);

        $validated = $request->validate([
            'nomor_induk' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('data_santri', 'nomor_induk')->ignore($santri->id_santri, 'id_santri'),
            ],
            'nama_lengkap_santri' => ['sometimes', 'string', 'max:200'],
            'kode_kelas' => [
                'sometimes',
                'string',
                'max:10',
                Rule::exists('data_kelas', 'kode_kelas')->where(fn($q) => $q->where('is_deleted', false)),
            ],
            'status' => ['nullable', 'string', 'max:20'],
            'tahun_masuk' => ['nullable', 'integer'],
            'tahun_lulus' => ['nullable', 'integer'],
            'jenis_kelamin' => ['nullable', 'string', 'size:1'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'berat_badan' => ['nullable', 'numeric'],
            'tinggi_badan' => ['nullable', 'numeric'],
            'gol_darah' => ['nullable', 'string', 'max:5'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kota_kabupaten' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'alamat_tinggal' => ['nullable', 'string'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_email' => ['nullable', 'email', 'max:100'],
            'nama_ayah_kandung' => ['nullable', 'string', 'max:200'],
            'nama_ibu_kandung' => ['nullable', 'string', 'max:200'],
            'nama_wali' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($santri, $validated) {
            $santri->update($validated);
            $santri->load(['kelas.unit', 'akun']);
                        $this->billingService->provisionBillingForActiveSantri($santri);
        });

        return response()->json([
            'message' => 'Data santri berhasil diperbarui.',
            'data' => $santri->fresh(['kelas', 'akun']),
        ]);
    }

    /**
     * Hapus data santri.
     */
    public function destroy(int $id): JsonResponse
    {
        $santri = DataSantri::where('is_deleted', false)->findOrFail($id);

        try {
            DB::transaction(function () use ($santri) {
                $santri->update([
                    'is_deleted' => true,
                    'deleted_at' => now(),
                ]);

                // Hapus akun santri jika ada
                DataAkunSantri::where('nomor_induk', $santri->nomor_induk)->delete();
            });
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data santri tidak dapat dihapus karena masih dipakai pada data pembayaran SPP atau data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data santri dan akun terkait berhasil dihapus.',
        ]);
    }

    /**
     * Buatkan akun untuk 1 santri dari master data santri.
     */
    public function buatAkun(int $id, Request $request): JsonResponse
    {
        $santri = DataSantri::with(['kelas', 'akun'])->where('is_deleted', false)->findOrFail($id);

        if ($santri->akun) {
            return response()->json([
                'message' => 'Santri ini sudah memiliki akun.',
            ], 422);
        }

        $validated = $request->validate([
            'nama_akun' => ['nullable', 'string', 'max:100', 'unique:data_akun_santri,nama_akun'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $namaAkun = $validated['nama_akun'] ?? $santri->nomor_induk;

        $akun = DataAkunSantri::create([
            'nomor_induk' => $santri->nomor_induk,
            'nama_akun' => $namaAkun,
            'nama_lengkap' => $santri->nama_lengkap_santri,
            'nama_unit' => $santri->kelas?->kode_unit,
            'nama_kelas' => $santri->kelas?->nama_kelas,
            'tahun_ajaran' => $santri->kelas?->tahun_ajaran,
            'alamat_email' => $santri->alamat_email,
            'nomor_telepon' => $santri->nomor_telepon,
            'password_hash' => Hash::make($validated['password']),
            'status' => strtoupper($validated['status'] ?? 'AKTIF'),
        ]);

        return response()->json([
            'message' => 'Akun santri berhasil dibuat.',
            'data' => $akun,
        ], 201);
    }

    /**
     * Pindah kelas massal santri.
     */
    public function pindahKelas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('data_santri', 'id_santri')->where(fn($q) => $q->where('is_deleted', false)),
            ],
            'kode_kelas' => [
                'required',
                'string',
                'max:10',
                Rule::exists('data_kelas', 'kode_kelas')->where(fn($q) => $q->where('is_deleted', false)),
            ],
        ]);

        $updated = 0;

        DB::transaction(function () use ($validated, &$updated) {
            $updated = DataSantri::where('is_deleted', false)
                ->whereIn('id_santri', $validated['ids'])
                ->update(['kode_kelas' => $validated['kode_kelas']]);
        });

        return response()->json([
            'message' => 'Pindah kelas massal berhasil diproses.',
            'data' => [
                'total_terupdate' => $updated,
                'kode_kelas_baru' => $validated['kode_kelas'],
            ],
        ]);
    }

    /**
     * Luluskan santri secara massal.
     */
    public function bulkLulus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => [
                'integer',
                Rule::exists('data_santri', 'id_santri')->where(fn($q) => $q->where('is_deleted', false)),
            ],
            'tahun_lulus' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($validated, &$updated) {
            $updated = DataSantri::where('is_deleted', false)
                ->whereIn('id_santri', $validated['ids'])
                ->update([
                    'status'      => 'LULUS',
                    'tahun_lulus' => $validated['tahun_lulus'],
                ]);
        });

        return response()->json([
            'message' => "Berhasil meluluskan {$updated} santri.",
            'data'    => ['total_terupdate' => $updated, 'tahun_lulus' => $validated['tahun_lulus']],
        ]);
    }

    /**
     * Batalkan kelulusan santri (kembalikan ke AKTIF, hapus tahun_lulus).
     */
    public function batalLulus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('data_santri', 'id_santri')->where(fn($q) => $q->where('is_deleted', false)),
            ],
        ]);

        $updated = 0;

        DB::transaction(function () use ($validated, &$updated) {
            $updated = DataSantri::where('is_deleted', false)
                ->whereIn('id_santri', $validated['ids'])
                ->where('status', 'LULUS')
                ->update([
                    'status'      => 'AKTIF',
                    'tahun_lulus' => null,
                ]);
        });

        return response()->json([
            'message' => "Berhasil membatalkan kelulusan {$updated} santri.",
            'data'    => ['total_terupdate' => $updated],
        ]);
    }


    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $file = $request->file('file');
        $fileExt = strtolower($file->getClientOriginalExtension());

        // Parse berdasarkan format file
        if (in_array($fileExt, ['xlsx', 'xls'])) {
            $parseResult = $this->parseExcelFile($file);
        } else {
            $parseResult = $this->parseCsvFile($file);
        }

        if (!$parseResult['success']) {
            return response()->json([
                'message' => $parseResult['error'],
            ], 422);
        }

        $headers = $parseResult['headers'];
        $rows = $parseResult['rows'];
        $normalizedHeaders = array_map([$this, 'normalizeImportHeader'], $headers);

        $inserted = 0;
        $updated = 0;
        $failed = [];
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            $rowData = $this->combineRowData($normalizedHeaders, $row);
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $payload = $this->mapSantriPayload($rowData);

            $validator = Validator::make($payload, [
                'nomor_induk' => ['required', 'string', 'max:20'],
                'nama_lengkap_santri' => ['required', 'string', 'max:200'],
                'kode_kelas' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::exists('data_kelas', 'kode_kelas')->where(fn($q) => $q->where('is_deleted', false)),
                ],
                'status' => ['nullable', 'string', 'max:20'],
                'tahun_masuk' => ['nullable', 'integer'],
                'tahun_lulus' => ['nullable', 'integer'],
                'jenis_kelamin' => ['nullable', 'string', 'size:1'],
                'tempat_lahir' => ['nullable', 'string', 'max:100'],
                'tanggal_lahir' => ['nullable', 'date'],
                'agama' => ['nullable', 'string', 'max:50'],
                'berat_badan' => ['nullable', 'numeric'],
                'tinggi_badan' => ['nullable', 'numeric'],
                'gol_darah' => ['nullable', 'string', 'max:5'],
                'provinsi' => ['nullable', 'string', 'max:100'],
                'kota_kabupaten' => ['nullable', 'string', 'max:100'],
                'kecamatan' => ['nullable', 'string', 'max:100'],
                'kelurahan' => ['nullable', 'string', 'max:100'],
                'alamat_tinggal' => ['nullable', 'string'],
                'nomor_telepon' => ['nullable', 'string', 'max:20'],
                'alamat_email' => ['nullable', 'email', 'max:100'],
                'nama_ayah_kandung' => ['nullable', 'string', 'max:200'],
                'nama_ibu_kandung' => ['nullable', 'string', 'max:200'],
                'nama_wali' => ['nullable', 'string', 'max:200'],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = DataSantri::where('nomor_induk', $payload['nomor_induk'])->first();

            if ($existing) {
                $existing->update(array_merge($payload, [
                    'is_deleted' => false,
                    'deleted_at' => null,
                ]));
                $existing->load(['kelas.unit', 'akun']);
                                $this->billingService->provisionBillingForActiveSantri($existing);
                $updated++;
                continue;
            }

            $payload['is_deleted'] = false;
            $payload['deleted_at'] = null;
            $data = DataSantri::create($payload);
            $data->load(['kelas.unit', 'akun']);
                        $this->billingService->provisionBillingForActiveSantri($data);
            $inserted++;
        }

        return response()->json([
            'message' => 'Import data santri selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data santri ke CSV sesuai filter.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataSantri::query()
            ->where('is_deleted', false)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('kode_kelas'), fn($q) => $q->where('kode_kelas', $request->kode_kelas))
            ->when($request->filled('kode_unit') || $request->filled('tahun_ajaran'), function ($q) use ($request) {
                $q->whereHas('kelas', function ($kelasQuery) use ($request) {
                    $kelasQuery
                        ->when($request->filled('kode_unit'), fn($subQuery) => $subQuery->where('kode_unit', $request->kode_unit))
                        ->when($request->filled('tahun_ajaran'), fn($subQuery) => $subQuery->where('tahun_ajaran', $request->tahun_ajaran));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_santri');

        $headers = [
            'nomor_induk',
            'nama_lengkap_santri',
            'kode_kelas',
            'status',
            'tahun_masuk',
            'tahun_lulus',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'agama',
            'berat_badan',
            'tinggi_badan',
            'gol_darah',
            'provinsi',
            'kota_kabupaten',
            'kecamatan',
            'kelurahan',
            'alamat_tinggal',
            'nomor_telepon',
            'alamat_email',
            'nama_ayah_kandung',
            'nama_ibu_kandung',
            'nama_wali',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->nomor_induk,
                        $row->nama_lengkap_santri,
                        $row->kode_kelas,
                        $row->status,
                        $row->tahun_masuk,
                        $row->tahun_lulus,
                        $row->jenis_kelamin,
                        $row->tempat_lahir,
                        optional($row->tanggal_lahir)->format('Y-m-d'),
                        $row->agama,
                        $row->berat_badan,
                        $row->tinggi_badan,
                        $row->gol_darah,
                        $row->provinsi,
                        $row->kota_kabupaten,
                        $row->kecamatan,
                        $row->kelurahan,
                        $row->alamat_tinggal,
                        $row->nomor_telepon,
                        $row->alamat_email,
                        $row->nama_ayah_kandung,
                        $row->nama_ibu_kandung,
                        $row->nama_wali,
                    ]);
                }
            });

            fclose($output);
        }, 'data-santri-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Template CSV/Excel import data santri.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'nomor_induk',
            'nama_lengkap_santri',
            'kode_kelas',
            'status',
            'tahun_masuk',
            'tahun_lulus',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'agama',
            'berat_badan',
            'tinggi_badan',
            'gol_darah',
            'provinsi',
            'kota_kabupaten',
            'kecamatan',
            'kelurahan',
            'alamat_tinggal',
            'nomor_telepon',
            'alamat_email',
            'nama_ayah_kandung',
            'nama_ibu_kandung',
            'nama_wali',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-santri.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * List data santri di trash.
     */
    public function trash(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataSantri::query()
            ->with(['kelas', 'akun'])
            ->where('is_deleted', true)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('kode_kelas'), fn($q) => $q->where('kode_kelas', $request->kode_kelas))
            ->when($request->filled('kode_unit') || $request->filled('tahun_ajaran'), function ($q) use ($request) {
                $q->whereHas('kelas', function ($kelasQuery) use ($request) {
                    $kelasQuery
                        ->when($request->filled('kode_unit'), fn($subQuery) => $subQuery->where('kode_unit', $request->kode_unit))
                        ->when($request->filled('tahun_ajaran'), fn($subQuery) => $subQuery->where('tahun_ajaran', $request->tahun_ajaran));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->orderByDesc('id_santri');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Pulihkan data santri dari trash.
     */
    public function restore(int $id): JsonResponse
    {
        $santri = DB::transaction(function () use ($id) {
            $santri = DataSantri::where('is_deleted', true)->findOrFail($id);

            $activeConflict = DataSantri::query()
                ->where('nomor_induk', $santri->nomor_induk)
                ->where('is_deleted', false)
                ->where('id_santri', '!=', $santri->id_santri)
                ->exists();

            if ($activeConflict) {
                return null;
            }

            $santri->update([
                'is_deleted' => false,
                'deleted_at' => null,
            ]);

            $santri->load(['kelas.unit', 'akun']);
                        $this->billingService->provisionBillingForActiveSantri($santri);

            return $santri;
        });

        if (!$santri) {
            return response()->json([
                'message' => 'Data santri tidak dapat dipulihkan karena nomor_induk sudah dipakai data aktif lain.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data santri berhasil dipulihkan.',
            'data' => $santri,
        ], 200);
    }

    /**
     * Ringkasan ketergantungan data santri.
     */
    public function dependencySummary(int $id): JsonResponse
    {
        $santri = DataSantri::findOrFail($id);
        $dependencies = $this->santriDependenciesByIdentity($santri->id_santri, $santri->nomor_induk);

        return response()->json([
            'data' => [
                'id_santri' => $santri->id_santri,
                'nomor_induk' => $santri->nomor_induk,
                'is_deleted' => (bool) $santri->is_deleted,
                'dependencies' => $dependencies,
                'can_force_delete' => $dependencies['total'] === 0,
            ],
        ]);
    }

    /**
     * Hapus permanen data santri dari trash.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $santri = DataSantri::where('is_deleted', true)->findOrFail($id);
        $dependencies = $this->santriDependenciesByIdentity($santri->id_santri, $santri->nomor_induk);

        if ($dependencies['total'] > 0) {
            return response()->json([
                'message' => 'Data santri tidak dapat dihapus permanen karena masih dipakai data lain.',
                'data' => [
                    'dependencies' => $dependencies,
                ],
            ], 422);
        }

        try {
            $santri->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data santri tidak dapat dihapus permanen karena masih dipakai data lain.',
                'data' => [
                    'dependencies' => $this->santriDependenciesByIdentity($santri->id_santri, $santri->nomor_induk),
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Data santri berhasil dihapus permanen.',
        ]);
    }



    private function santriDependenciesByIdentity(int $idSantri, string $nomorInduk): array
    {
        $safeCount = function (string $table, string $column, mixed $value): int {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                return 0;
            }

            return DB::table($table)->where($column, $value)->count();
        };

        $dependencies = [
            'absensi_santri' => $safeCount('absensi_santri', 'nomor_induk', $nomorInduk),
            'data_akun_santri' => $safeCount('data_akun_santri', 'nomor_induk', $nomorInduk),
            'pembayaran_spp' => $safeCount('pembayaran_spp', 'id_santri', $idSantri),
            'administrasi_bebas' => $safeCount('administrasi_bebas', 'id_santri', $idSantri),
            'spp_setting' => $safeCount('spp_setting', 'id_santri', $idSantri),
        ];

        $dependencies['total'] = array_sum($dependencies);

        return $dependencies;
    }

    /**
     * Parse file CSV.
     */
    private function parseCsvFile($file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [
                'success' => false,
                'error' => 'File CSV tidak dapat dibaca.',
            ];
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return [
                'success' => false,
                'error' => 'Header CSV tidak ditemukan.',
            ];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return [
            'success' => true,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Parse file Excel (.xlsx, .xls).
     */
    private function parseExcelFile($file): array
    {
        $ioFactoryClass = 'PhpOffice\\PhpSpreadsheet\\IOFactory';

        if (!class_exists($ioFactoryClass)) {
            return [
                'success' => false,
                'error' => 'Dependensi pembaca Excel tidak ditemukan. Jalankan composer install/update untuk memasang phpoffice/phpspreadsheet.',
            ];
        }

        try {
            $spreadsheet = $ioFactoryClass::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            if (empty($data)) {
                return [
                    'success' => false,
                    'error' => 'File Excel kosong atau tidak dapat dibaca.',
                ];
            }

            $headers = array_shift($data);

            if (empty($headers)) {
                return [
                    'success' => false,
                    'error' => 'Header Excel tidak ditemukan.',
                ];
            }

            // Filter rows yang kosong
            $rows = array_filter($data, function ($row) {
                return !empty(array_filter($row));
            });

            return [
                'success' => true,
                'headers' => $headers,
                'rows' => array_values($rows),
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'error' => 'File Excel tidak dapat diproses: ' . $exception->getMessage(),
            ];
        }
    }

    private function normalizeImportHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace([' ', '-', '/'], '_', $normalized);

        return preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
    }

    private function combineRowData(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $data[$header] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }

    private function mapSantriPayload(array $rowData): array
    {
        return [
            'nomor_induk' => $rowData['nomor_induk'] ?? null,
            'nama_lengkap_santri' => $rowData['nama_lengkap_santri'] ?? null,
            'kode_kelas' => $rowData['kode_kelas'] ?? null,
            'status' => $rowData['status'] ?? null,
            'tahun_masuk' => $rowData['tahun_masuk'] ?? null,
            'tahun_lulus' => $rowData['tahun_lulus'] ?? null,
            'jenis_kelamin' => $rowData['jenis_kelamin'] ?? null,
            'tempat_lahir' => $rowData['tempat_lahir'] ?? null,
            'tanggal_lahir' => $rowData['tanggal_lahir'] ?? null,
            'agama' => $rowData['agama'] ?? null,
            'berat_badan' => $rowData['berat_badan'] ?? null,
            'tinggi_badan' => $rowData['tinggi_badan'] ?? null,
            'gol_darah' => $rowData['gol_darah'] ?? null,
            'provinsi' => $rowData['provinsi'] ?? null,
            'kota_kabupaten' => $rowData['kota_kabupaten'] ?? null,
            'kecamatan' => $rowData['kecamatan'] ?? null,
            'kelurahan' => $rowData['kelurahan'] ?? null,
            'alamat_tinggal' => $rowData['alamat_tinggal'] ?? null,
            'nomor_telepon' => $rowData['nomor_telepon'] ?? null,
            'alamat_email' => $rowData['alamat_email'] ?? null,
            'nama_ayah_kandung' => $rowData['nama_ayah_kandung'] ?? null,
            'nama_ibu_kandung' => $rowData['nama_ibu_kandung'] ?? null,
            'nama_wali' => $rowData['nama_wali'] ?? null,
        ];
    }
}
