<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataKelas;
use App\Models\DataKelasMapel;
use App\Models\DataMataPelajaran;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataKelasMapelController extends Controller
{
    /**
     * List data kelas mapel.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataKelasMapel::query()
            ->with(['kelas', 'mataPelajaran', 'petugas'])
            ->when($request->filled('kode_kelas'), fn ($q) => $q->where('kode_kelas', strtoupper($request->kode_kelas)))
            ->when($request->filled('kode_mapel'), fn ($q) => $q->where('kode_mapel', strtoupper($request->kode_mapel)))
            ->when($request->filled('id_petugas'), fn ($q) => $q->where('id_petugas', (int) $request->id_petugas))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', (int) $request->semester))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('kode_unit'), function ($q) use ($request) {
                $q->whereHas('kelas', function ($subQuery) use ($request) {
                    $subQuery->where('kode_unit', strtoupper($request->kode_unit));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('kode_mapel', 'like', "%{$keyword}%")
                        ->orWhere('tahun_ajaran', 'like', "%{$keyword}%")
                        ->orWhere('buku_acuan', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('kode_kelas')
            ->orderBy('semester')
            ->orderBy('kode_mapel');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data kelas mapel baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge($this->normalizeKelasMapelInput($request->all()));

        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kode_mapel' => ['required', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'buku_acuan' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeKelasMapelInput($validated);

        $this->validateUnitConsistency($validated['kode_kelas'], $validated['kode_mapel']);
        $this->validateUniqueCombination($validated, null);

        if (!isset($validated['status']) || strtoupper($validated['status']) === 'AKTIF') {
            $this->validateParentActive($validated['kode_kelas'], $validated['kode_mapel']);
        }

        $data = DataKelasMapel::create($validated);

        return response()->json([
            'message' => 'Data kelas mapel berhasil dibuat.',
            'data' => $data->fresh(['kelas', 'mataPelajaran', 'petugas']),
        ], 201);
    }

    /**
     * Tampilkan detail data kelas mapel.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataKelasMapel::with(['kelas', 'mataPelajaran', 'petugas'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data kelas mapel.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kelasMapel = DataKelasMapel::findOrFail($id);

        $request->merge($this->normalizeKelasMapelInput($request->all()));

        $validated = $request->validate([
            'kode_kelas' => ['sometimes', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kode_mapel' => ['sometimes', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tahun_ajaran' => [
                'sometimes',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'semester' => ['sometimes', 'integer', Rule::in([1, 2])],
            'buku_acuan' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeKelasMapelInput($validated);

        $merged = [
            'kode_kelas' => $validated['kode_kelas'] ?? $kelasMapel->kode_kelas,
            'kode_mapel' => $validated['kode_mapel'] ?? $kelasMapel->kode_mapel,
            'tahun_ajaran' => $validated['tahun_ajaran'] ?? $kelasMapel->tahun_ajaran,
            'semester' => $validated['semester'] ?? $kelasMapel->semester,
        ];

        $this->validateUnitConsistency($merged['kode_kelas'], $merged['kode_mapel']);
        $this->validateUniqueCombination($merged, $kelasMapel->id_kelas_mapel);

        $newStatus = $validated['status'] ?? $kelasMapel->status;
        if (strtoupper($newStatus) === 'AKTIF') {
            $this->validateParentActive($merged['kode_kelas'], $merged['kode_mapel']);
        }

        if (array_key_exists('id_petugas', $validated) && $validated['id_petugas'] !== null && $validated['id_petugas'] != $kelasMapel->id_petugas) {
            $this->validatePengajarConflict($validated['id_petugas'], $merged['tahun_ajaran'], $kelasMapel->id_kelas_mapel);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($kelasMapel, $validated) {
            $kelasMapel->update($validated);

            if (array_key_exists('status', $validated) && strtoupper($validated['status']) === 'NONAKTIF') {
                // Update all associated Jadwal Pembelajaran to NONAKTIF
                \App\Models\JadwalPembelajaran::where('id_kelas_mapel', $kelasMapel->id_kelas_mapel)
                    ->update(['status' => 'NONAKTIF']);
            }
        });

        return response()->json([
            'message' => 'Data kelas mapel berhasil diperbarui.',
            'data' => $kelasMapel->fresh(['kelas', 'mataPelajaran', 'petugas']),
        ]);
    }

    /**
     * Hapus data kelas mapel.
     */
    public function destroy(int $id): JsonResponse
    {
        $kelasMapel = DataKelasMapel::findOrFail($id);

        try {
            $kelasMapel->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data kelas mapel tidak dapat dihapus karena masih dipakai pada jadwal pembelajaran atau data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data kelas mapel berhasil dihapus.',
        ]);
    }

    /**
     * Import data kelas mapel dari CSV (upsert berdasarkan kombinasi unik).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

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

            $payload = $this->mapKelasMapelPayload($rowData);
            $payload = $this->normalizeKelasMapelInput($payload);

            // Konversi nama_petugas ke id_petugas
            if (!empty($payload['nama_petugas'])) {
                $petugas = \App\Models\DataPetugas::where('nama_lengkap', trim($payload['nama_petugas']))
                    ->where('status', 'AKTIF')
                    ->first();
                if ($petugas) {
                    $payload['id_petugas'] = $petugas->id_petugas;
                } else {
                    $failed[] = [
                        'line' => $lineNumber,
                        'errors' => ['Pengajar dengan nama "' . $payload['nama_petugas'] . '" tidak ditemukan atau tidak aktif.'],
                    ];
                    continue;
                }
            } else {
                $payload['id_petugas'] = null;
            }
            unset($payload['nama_petugas']);

            $validator = Validator::make($payload, [
                'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
                'kode_mapel' => ['required', 'string', 'max:20', 'exists:data_mata_pelajaran,kode_mapel'],
                'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
                'tahun_ajaran' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
                ],
                'semester' => ['required', 'integer', Rule::in([1, 2])],
                'buku_acuan' => ['nullable', 'string', 'max:200'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            try {
                $this->validateUnitConsistency($payload['kode_kelas'], $payload['kode_mapel']);
            } catch (ValidationException $exception) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $exception->errors()['kode_unit'] ?? ['Kode unit kelas harus sama dengan kode unit mata pelajaran.'],
                ];
                continue;
            }

            $existing = DataKelasMapel::query()
                ->where('kode_kelas', $payload['kode_kelas'])
                ->where('kode_mapel', $payload['kode_mapel'])
                ->where('tahun_ajaran', $payload['tahun_ajaran'])
                ->where('semester', $payload['semester'])
                ->first();

            if ($existing && !empty($payload['id_petugas']) && $existing->id_petugas != $payload['id_petugas']) {
                try {
                    $this->validatePengajarConflict((int) $payload['id_petugas'], $payload['tahun_ajaran'], $existing->id_kelas_mapel);
                } catch (ValidationException $exception) {
                    $failed[] = [
                        'line' => $lineNumber,
                        'errors' => $exception->errors()['id_petugas'] ?? ['Pengajar bentrok dengan jadwal lain.'],
                    ];
                    continue;
                }
            }

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            DataKelasMapel::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data kelas mapel selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data kelas mapel ke CSV sesuai filter.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataKelasMapel::query()
            ->when($request->filled('kode_kelas'), fn ($q) => $q->where('kode_kelas', strtoupper($request->kode_kelas)))
            ->when($request->filled('kode_mapel'), fn ($q) => $q->where('kode_mapel', strtoupper($request->kode_mapel)))
            ->when($request->filled('id_petugas'), fn ($q) => $q->where('id_petugas', (int) $request->id_petugas))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', (int) $request->semester))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('kode_unit'), function ($q) use ($request) {
                $q->whereHas('kelas', function ($subQuery) use ($request) {
                    $subQuery->where('kode_unit', strtoupper($request->kode_unit));
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('kode_mapel', 'like', "%{$keyword}%")
                        ->orWhere('tahun_ajaran', 'like', "%{$keyword}%")
                        ->orWhere('buku_acuan', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('kode_kelas')
            ->orderBy('semester')
            ->orderBy('kode_mapel');

        $headers = [
            'kode_kelas',
            'kode_mapel',
            'nama_petugas',
            'tahun_ajaran',
            'semester',
            'buku_acuan',
            'status',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->kode_kelas,
                        $row->kode_mapel,
                        $row->petugas ? $row->petugas->nama_lengkap : null,
                        $row->tahun_ajaran,
                        $row->semester,
                        $row->buku_acuan,
                        $row->status,
                    ]);
                }
            });

            fclose($output);
        }, 'data-kelas-mapel-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Template CSV import data kelas mapel.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'kode_kelas',
            'kode_mapel',
            'nama_petugas',
            'tahun_ajaran',
            'semester',
            'buku_acuan',
            'status',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-kelas-mapel.csv', [
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

    private function mapKelasMapelPayload(array $rowData): array
    {
        return [
            'kode_kelas' => $rowData['kode_kelas'] ?? null,
            'kode_mapel' => $rowData['kode_mapel'] ?? null,
            'nama_petugas' => $rowData['nama_petugas'] ?? null,
            'tahun_ajaran' => $rowData['tahun_ajaran'] ?? null,
            'semester' => $rowData['semester'] ?? null,
            'buku_acuan' => $rowData['buku_acuan'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }

    private function normalizeKelasMapelInput(array $payload): array
    {
        foreach (['kode_kelas', 'kode_mapel', 'status'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = strtoupper(trim($payload[$field]));
            }
        }

        if (array_key_exists('tahun_ajaran', $payload) && is_string($payload['tahun_ajaran'])) {
            $payload['tahun_ajaran'] = trim($payload['tahun_ajaran']);
        }

        return $payload;
    }

    private function validateUniqueCombination(array $payload, ?int $ignoreId): void
    {
        $query = DataKelasMapel::query()
            ->where('kode_kelas', $payload['kode_kelas'])
            ->where('kode_mapel', $payload['kode_mapel'])
            ->where('tahun_ajaran', $payload['tahun_ajaran'])
            ->where('semester', $payload['semester']);

        if ($ignoreId !== null) {
            $query->where('id_kelas_mapel', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'kombinasi' => ['Kombinasi kelas-mapel-tahun ajaran-semester sudah terdaftar.'],
            ]);
        }
    }

    private function validateUnitConsistency(string $kodeKelas, string $kodeMapel): void
    {
        $kelas = DataKelas::query()
            ->select(['kode_kelas', 'kode_unit'])
            ->where('kode_kelas', $kodeKelas)
            ->first();

        $mapel = DataMataPelajaran::query()
            ->select(['kode_mapel', 'kode_unit'])
            ->where('kode_mapel', $kodeMapel)
            ->first();

        if (!$kelas || !$mapel) {
            return;
        }

        if (strtoupper((string) $kelas->kode_unit) !== strtoupper((string) $mapel->kode_unit)) {
            throw ValidationException::withMessages([
                'kode_unit' => [
                    "Kode unit pada kelas ({$kelas->kode_unit}) harus sama dengan kode unit pada mata pelajaran ({$mapel->kode_unit}).",
                ],
            ]);
        }
    }

    private function validateParentActive(string $kodeKelas, string $kodeMapel): void
    {
        $kelas = DataKelas::where('kode_kelas', $kodeKelas)->where('is_deleted', false)->first();
        if ($kelas && strtoupper($kelas->status) === 'NONAKTIF') {
            throw ValidationException::withMessages([
                'status' => ["Tidak dapat mengaktifkan kelas mapel karena kelas terkait ({$kelas->nama_kelas}) masih NONAKTIF."],
            ]);
        }

        $mapel = DataMataPelajaran::where('kode_mapel', $kodeMapel)->first();
        if ($mapel && strtoupper($mapel->status) === 'NONAKTIF') {
            throw ValidationException::withMessages([
                'status' => ["Tidak dapat mengaktifkan kelas mapel karena mata pelajaran terkait ({$mapel->nama_mapel}) masih NONAKTIF."],
            ]);
        }
    }

    private function validatePengajarConflict(int $idPetugas, string $tahunAjaran, int $idKelasMapel): void
    {
        $jadwalList = \App\Models\JadwalPembelajaran::where('id_kelas_mapel', $idKelasMapel)
            ->where('status', 'AKTIF')
            ->get();

        if ($jadwalList->isEmpty()) {
            return;
        }

        foreach ($jadwalList as $jadwal) {
            $conflict = \App\Models\JadwalPembelajaran::with('kelasMapel')
                ->whereHas('kelasMapel', function ($q) use ($idPetugas, $idKelasMapel) {
                    $q->where('id_petugas', $idPetugas)
                      ->where('id_kelas_mapel', '!=', $idKelasMapel);
                })
                ->where('status', 'AKTIF')
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('hari', $jadwal->hari)
                ->where(function ($query) use ($jadwal) {
                    $query->where('jam_mulai', '<', $jadwal->jam_selesai)
                          ->where('jam_selesai', '>', $jadwal->jam_mulai);
                })
                ->first();

            if ($conflict) {
                $kelasConflict = $conflict->kelasMapel ? $conflict->kelasMapel->kode_kelas : 'lain';
                $mapelConflict = $conflict->kelasMapel ? $conflict->kelasMapel->kode_mapel : '';
                throw ValidationException::withMessages([
                    'id_petugas' => [
                        "Pengajar bentrok! Pengajar sudah memiliki jadwal {$mapelConflict} di kelas {$kelasConflict} pada hari {$jadwal->hari} jam " . substr($conflict->jam_mulai, 0, 5) . " - " . substr($conflict->jam_selesai, 0, 5) . "."
                    ],
                ]);
            }
        }
    }
}
