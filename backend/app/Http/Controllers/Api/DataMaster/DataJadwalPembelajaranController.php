<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Exports\DataJadwalPembelajaranExport;
use App\Http\Controllers\Controller;
use App\Imports\DataJadwalPembelajaranImport;
use App\Models\JadwalPembelajaran;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataJadwalPembelajaranController extends Controller
{
    /**
     * List data jadwal pembelajaran.
     *
     * @queryParam nomor_induk string Filter jadwal berdasarkan nomor induk santri. Example: 12345
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = JadwalPembelajaran::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas'])
            ->when($request->filled('id_kelas_mapel'), fn ($q) => $q->where('id_kelas_mapel', (int) $request->id_kelas_mapel))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', trim((string) $request->tahun_ajaran)))
            ->when($request->filled('hari'), fn ($q) => $q->where('hari', strtoupper(trim((string) $request->hari))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper(trim((string) $request->status))))
            ->when($request->filled('kode_unit'), function ($q) use ($request) {
                $q->whereHas('kelasMapel.kelas', function ($subQuery) use ($request) {
                    $subQuery->where('kode_unit', strtoupper(trim((string) $request->kode_unit)));
                });
            })
            ->when($request->filled('kode_kelas'), function ($q) use ($request) {
                $q->whereHas('kelasMapel', function ($subQuery) use ($request) {
                    $subQuery->where('kode_kelas', strtoupper(trim((string) $request->kode_kelas)));
                });
            })
            ->when($request->filled('id_petugas'), function ($q) use ($request) {
                $q->whereHas('kelasMapel', function ($subQuery) use ($request) {
                    $subQuery->where('id_petugas', (int) $request->id_petugas);
                });
            })
            ->when($request->filled('kode_mapel'), function ($q) use ($request) {
                $q->whereHas('kelasMapel', function ($subQuery) use ($request) {
                    $subQuery->where('kode_mapel', strtoupper(trim((string) $request->kode_mapel)));
                });
            })
            ->when($request->filled('nomor_induk'), function ($q) use ($request) {
                $nomorInduk = trim((string) $request->nomor_induk);
                $q->whereHas('kelasMapel.kelas.santri', function ($subQuery) use ($nomorInduk) {
                    $subQuery->where('nomor_induk', $nomorInduk);
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('tahun_ajaran', 'like', "%{$keyword}%")
                        ->orWhere('hari', 'like', "%{$keyword}%")
                        ->orWhere('ruangan', 'like', "%{$keyword}%")
                        ->orWhere('jam_mulai', 'like', "%{$keyword}%")
                        ->orWhere('jam_selesai', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('tahun_ajaran')
            ->orderBy('hari')
            ->orderBy('jam_mulai');

        return response()->json($query->paginate($perPage));
    }

    /**
     * List data jadwal pembelajaran berdasarkan nomor induk santri.
     *
     * @urlParam nomor_induk string required Nomor induk santri. Example: 12345
     * @queryParam per_page integer Jumlah data per halaman. Example: 10
     * @queryParam tahun_ajaran string Filter tahun ajaran. Example: 2024/2025
     * @queryParam hari string Filter hari. Example: SENIN
     * @queryParam status string Filter status. Example: AKTIF
     * @queryParam q string Kata kunci pencarian.
     */
    public function byNomorInduk(Request $request, string $nomorInduk): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $nomorInduk = trim($nomorInduk);

        $query = JadwalPembelajaran::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas'])
            ->whereHas('kelasMapel.kelas.santri', function ($subQuery) use ($nomorInduk) {
                $subQuery->where('nomor_induk', $nomorInduk);
            })
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', trim((string) $request->tahun_ajaran)))
            ->when($request->filled('hari'), fn ($q) => $q->where('hari', strtoupper(trim((string) $request->hari))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper(trim((string) $request->status))))
            ->when($request->filled('kode_unit'), function ($q) use ($request) {
                $q->whereHas('kelasMapel.kelas', function ($subQuery) use ($request) {
                    $subQuery->where('kode_unit', strtoupper(trim((string) $request->kode_unit)));
                });
            })
            ->when($request->filled('kode_kelas'), function ($q) use ($request) {
                $q->whereHas('kelasMapel', function ($subQuery) use ($request) {
                    $subQuery->where('kode_kelas', strtoupper(trim((string) $request->kode_kelas)));
                });
            })
            ->when($request->filled('id_petugas'), function ($q) use ($request) {
                $q->whereHas('kelasMapel', function ($subQuery) use ($request) {
                    $subQuery->where('id_petugas', (int) $request->id_petugas);
                });
            })
            ->when($request->filled('kode_mapel'), function ($q) use ($request) {
                $q->whereHas('kelasMapel', function ($subQuery) use ($request) {
                    $subQuery->where('kode_mapel', strtoupper(trim((string) $request->kode_mapel)));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('tahun_ajaran', 'like', "%{$keyword}%")
                        ->orWhere('hari', 'like', "%{$keyword}%")
                        ->orWhere('ruangan', 'like', "%{$keyword}%")
                        ->orWhere('jam_mulai', 'like', "%{$keyword}%")
                        ->orWhere('jam_selesai', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('tahun_ajaran')
            ->orderBy('hari')
            ->orderBy('jam_mulai');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data jadwal pembelajaran baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge($this->normalizeJadwalInput($request->all()));

        $validated = $request->validate([
            'id_kelas_mapel' => ['required', 'integer', 'exists:data_kelas_mapel,id_kelas_mapel'],
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'hari' => ['required', 'string', 'max:10'],
            'jam_mulai' => ['required', 'date_format:H:i:s'],
            'jam_selesai' => ['required', 'date_format:H:i:s', 'after:jam_mulai'],
            'ruangan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeJadwalInput($validated);
        $this->validateUniqueCombination($validated, null);

        if (!isset($validated['status']) || strtoupper($validated['status']) === 'AKTIF') {
            $this->validateParentActive((int)$validated['id_kelas_mapel']);
        }

        $data = JadwalPembelajaran::create($validated);

        return response()->json([
            'message' => 'Data jadwal pembelajaran berhasil dibuat.',
            'data' => $data->fresh(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas']),
        ], 201);
    }

    /**
     * Tampilkan detail data jadwal pembelajaran.
     */
    public function show(int $id): JsonResponse
    {
        $data = JadwalPembelajaran::with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas'])
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data jadwal pembelajaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jadwal = JadwalPembelajaran::findOrFail($id);

        $request->merge($this->normalizeJadwalInput($request->all()));

        $validated = $request->validate([
            'id_kelas_mapel' => ['sometimes', 'integer', 'exists:data_kelas_mapel,id_kelas_mapel'],
            'tahun_ajaran' => [
                'sometimes',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'hari' => ['sometimes', 'string', 'max:10'],
            'jam_mulai' => ['sometimes', 'date_format:H:i:s'],
            'jam_selesai' => ['sometimes', 'date_format:H:i:s'],
            'ruangan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeJadwalInput($validated);

        $merged = [
            'id_kelas_mapel' => $validated['id_kelas_mapel'] ?? $jadwal->id_kelas_mapel,
            'tahun_ajaran' => $validated['tahun_ajaran'] ?? $jadwal->tahun_ajaran,
            'hari' => $validated['hari'] ?? $jadwal->hari,
            'jam_mulai' => $validated['jam_mulai'] ?? $jadwal->jam_mulai,
            'jam_selesai' => $validated['jam_selesai'] ?? $jadwal->jam_selesai,
            'ruangan' => array_key_exists('ruangan', $validated) ? $validated['ruangan'] : $jadwal->ruangan,
            'status' => $validated['status'] ?? $jadwal->status,
        ];

        $this->validateUniqueCombination($merged, $jadwal->id_jadwal);

        $jamMulai = $validated['jam_mulai'] ?? $jadwal->jam_mulai;
        $jamSelesai = $validated['jam_selesai'] ?? $jadwal->jam_selesai;
        if ($jamMulai >= $jamSelesai) {
            throw ValidationException::withMessages([
                'jam_selesai' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        $newStatus = $validated['status'] ?? $jadwal->status;
        if (strtoupper($newStatus) === 'AKTIF') {
            $this->validateParentActive((int)$merged['id_kelas_mapel']);
        }

        $jadwal->update($validated);

        return response()->json([
            'message' => 'Data jadwal pembelajaran berhasil diperbarui.',
            'data' => $jadwal->fresh(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas']),
        ]);
    }

    /**
     * Hapus data jadwal pembelajaran.
     */
    public function destroy(int $id): JsonResponse
    {
        $jadwal = JadwalPembelajaran::findOrFail($id);

        try {
            $jadwal->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data jadwal pembelajaran tidak dapat dihapus karena masih dipakai pada data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data jadwal pembelajaran berhasil dihapus.',
        ]);
    }

    /**
     * Import data jadwal pembelajaran dari CSV (upsert berdasarkan kombinasi unik).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $import = new DataJadwalPembelajaranImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Import data jadwal pembelajaran selesai.',
                'data' => $import->result(),
            ]);
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json([
                'message' => 'File CSV tidak dapat dibaca.',
            ], 422);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return response()->json([
                'message' => 'Header CSV tidak ditemukan.',
            ], 422);
        }

        $normalizedHeaders = array_map([$this, 'normalizeImportHeader'], $headers);

        $inserted = 0;
        $updated = 0;
        $failed = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $rowData = $this->combineRowData($normalizedHeaders, $row);
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $payload = $this->mapJadwalPayload($rowData);
            $payload = $this->normalizeJadwalInput($payload);

            // Lookup id_kelas_mapel from kode_kelas and kode_mapel
            if (!empty($payload['kode_kelas']) && !empty($payload['kode_mapel'])) {
                $kelasMapel = \App\Models\DataKelasMapel::where('kode_kelas', trim($payload['kode_kelas']))
                    ->where('kode_mapel', trim($payload['kode_mapel']))
                    ->where('status', 'AKTIF')
                    ->first();
                if ($kelasMapel) {
                    $payload['id_kelas_mapel'] = $kelasMapel->id_kelas_mapel;
                } else {
                    $failed[] = [
                        'line' => $lineNumber,
                        'errors' => ["Kelas Mapel dengan Kode Kelas '{$payload['kode_kelas']}' dan Kode Mapel '{$payload['kode_mapel']}' tidak ditemukan atau tidak aktif."],
                    ];
                    continue;
                }
            } else {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => ['Kolom kode_kelas dan kode_mapel wajib diisi.'],
                ];
                continue;
            }
            unset($payload['kode_kelas'], $payload['kode_mapel']);

            $validator = Validator::make($payload, [
                'id_kelas_mapel' => ['required', 'integer', 'exists:data_kelas_mapel,id_kelas_mapel'],
                'tahun_ajaran' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
                ],
                'hari' => ['required', 'string', 'max:10'],
                'jam_mulai' => ['required', 'date_format:H:i:s'],
                'jam_selesai' => ['required', 'date_format:H:i:s', 'after:jam_mulai'],
                'ruangan' => ['nullable', 'string', 'max:50'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = JadwalPembelajaran::query()
                ->where('id_kelas_mapel', $payload['id_kelas_mapel'])
                ->where('tahun_ajaran', $payload['tahun_ajaran'])
                ->where('hari', $payload['hari'])
                ->where('jam_mulai', $payload['jam_mulai'])
                ->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            JadwalPembelajaran::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data jadwal pembelajaran selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data jadwal pembelajaran ke CSV sesuai filter.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new DataJadwalPembelajaranExport(
                idKelasMapel: $request->filled('id_kelas_mapel') ? (int) $request->id_kelas_mapel : null,
                tahunAjaran: $request->filled('tahun_ajaran') ? (string) $request->tahun_ajaran : null,
                hari: $request->filled('hari') ? (string) $request->hari : null,
                status: $request->filled('status') ? (string) $request->status : null,
                keyword: $request->filled('q') ? (string) $request->q : null,
            ),
            'data-jadwal-pembelajaran-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Template CSV import data jadwal pembelajaran.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'kode_kelas',
            'kode_mapel',
            'tahun_ajaran',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'ruangan',
            'status',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-jadwal-pembelajaran.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

    private function mapJadwalPayload(array $rowData): array
    {
        return [
            'kode_kelas' => $rowData['kode_kelas'] ?? null,
            'kode_mapel' => $rowData['kode_mapel'] ?? null,
            'tahun_ajaran' => $rowData['tahun_ajaran'] ?? null,
            'hari' => $rowData['hari'] ?? null,
            'jam_mulai' => $rowData['jam_mulai'] ?? null,
            'jam_selesai' => $rowData['jam_selesai'] ?? null,
            'ruangan' => $rowData['ruangan'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }

    private function normalizeJadwalInput(array $payload): array
    {
        foreach (['hari', 'status'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = strtoupper(trim($payload[$field]));
            }
        }

        if (array_key_exists('tahun_ajaran', $payload) && is_string($payload['tahun_ajaran'])) {
            $payload['tahun_ajaran'] = trim($payload['tahun_ajaran']);
        }

        if (array_key_exists('ruangan', $payload) && is_string($payload['ruangan'])) {
            $payload['ruangan'] = trim($payload['ruangan']);
        }

        return $payload;
    }

    private function validateUniqueCombination(array $payload, ?int $ignoreId): void
    {
        if (array_key_exists('status', $payload) && $payload['status'] !== null && strtoupper($payload['status']) === 'NONAKTIF') {
            return;
        }

        $kelasMapel = \App\Models\DataKelasMapel::findOrFail($payload['id_kelas_mapel']);
        $kodeKelas = $kelasMapel->kode_kelas;
        $idPetugas = $kelasMapel->id_petugas;
        $hari = $payload['hari'];
        $jamMulai = $payload['jam_mulai'];
        $jamSelesai = $payload['jam_selesai'];

        // 1. Cek bentrok kelas
        $bentrokKelas = JadwalPembelajaran::query()
            ->where('tahun_ajaran', $payload['tahun_ajaran'])
            ->where('hari', $hari)
            ->where('status', 'AKTIF')
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->where('jam_mulai', '<', $jamSelesai)
                  ->where('jam_selesai', '>', $jamMulai);
            })
            ->whereHas('kelasMapel', function ($sub) use ($kodeKelas) {
                $sub->where('kode_kelas', $kodeKelas);
            })
            ->when($ignoreId !== null, fn($q) => $q->where('id_jadwal', '!=', $ignoreId))
            ->first();

        if ($bentrokKelas) {
            $namaKelas = optional($bentrokKelas->kelasMapel?->kelas)->nama_kelas ?? $kodeKelas;
            $namaMapel = optional($bentrokKelas->kelasMapel?->mataPelajaran)->nama_mapel ?? '';
            throw ValidationException::withMessages([
                'jam_mulai' => ["Jadwal bentrok dengan kelas {$namaKelas} untuk mata pelajaran {$namaMapel} ({$bentrokKelas->jam_mulai} - {$bentrokKelas->jam_selesai})."],
            ]);
        }

        // 2. Cek bentrok pengajar/guru
        if ($idPetugas) {
            $bentrokGuru = JadwalPembelajaran::query()
                ->where('tahun_ajaran', $payload['tahun_ajaran'])
                ->where('hari', $hari)
                ->where('status', 'AKTIF')
                ->where(function ($q) use ($jamMulai, $jamSelesai) {
                    $q->where('jam_mulai', '<', $jamSelesai)
                      ->where('jam_selesai', '>', $jamMulai);
                })
                ->whereHas('kelasMapel', function ($sub) use ($idPetugas) {
                    $sub->where('id_petugas', $idPetugas);
                })
                ->when($ignoreId !== null, fn($q) => $q->where('id_jadwal', '!=', $ignoreId))
                ->first();

            if ($bentrokGuru) {
                $namaGuru = optional($bentrokGuru->kelasMapel?->petugas)->nama_lengkap ?? 'Petugas';
                $namaMapel = optional($bentrokGuru->kelasMapel?->mataPelajaran)->nama_mapel ?? '';
                throw ValidationException::withMessages([
                    'jam_mulai' => ["Jadwal bentrok bagi pengajar {$namaGuru} pada mata pelajaran {$namaMapel} ({$bentrokGuru->jam_mulai} - {$bentrokGuru->jam_selesai})."],
                ]);
            }
        }

        // 3. Cek bentrok ruangan
        if (!empty($payload['ruangan'])) {
            $bentrokRuangan = JadwalPembelajaran::query()
                ->where('tahun_ajaran', $payload['tahun_ajaran'])
                ->where('hari', $hari)
                ->where('status', 'AKTIF')
                ->whereRaw('UPPER(ruangan) = ?', [strtoupper(trim($payload['ruangan']))])
                ->where(function ($q) use ($jamMulai, $jamSelesai) {
                    $q->where('jam_mulai', '<', $jamSelesai)
                      ->where('jam_selesai', '>', $jamMulai);
                })
                ->when($ignoreId !== null, fn($q) => $q->where('id_jadwal', '!=', $ignoreId))
                ->first();

            if ($bentrokRuangan) {
                $namaMapel = optional($bentrokRuangan->kelasMapel?->mataPelajaran)->nama_mapel ?? '';
                throw ValidationException::withMessages([
                    'ruangan' => ["Ruangan {$payload['ruangan']} sudah digunakan oleh mata pelajaran {$namaMapel} ({$bentrokRuangan->jam_mulai} - {$bentrokRuangan->jam_selesai})."],
                ]);
            }
        }
    }

    private function validateParentActive(int $idKelasMapel): void
    {
        $kelasMapel = \App\Models\DataKelasMapel::with(['kelas', 'mataPelajaran'])->find($idKelasMapel);
        if (!$kelasMapel) {
            return;
        }

        if (strtoupper($kelasMapel->status) === 'NONAKTIF') {
            throw ValidationException::withMessages([
                'status' => ["Tidak dapat mengaktifkan jadwal karena mata pelajaran kelas terkait masih NONAKTIF."],
            ]);
        }

        if ($kelasMapel->kelas && strtoupper($kelasMapel->kelas->status) === 'NONAKTIF') {
            throw ValidationException::withMessages([
                'status' => ["Tidak dapat mengaktifkan jadwal karena kelas terkait ({$kelasMapel->kelas->nama_kelas}) masih NONAKTIF."],
            ]);
        }

        if ($kelasMapel->mataPelajaran && strtoupper($kelasMapel->mataPelajaran->status) === 'NONAKTIF') {
            throw ValidationException::withMessages([
                'status' => ["Tidak dapat mengaktifkan jadwal karena mata pelajaran terkait ({$kelasMapel->mataPelajaran->nama_mapel}) masih NONAKTIF."],
            ]);
        }
    }
}
