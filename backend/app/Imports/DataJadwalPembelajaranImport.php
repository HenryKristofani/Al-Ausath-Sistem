<?php

namespace App\Imports;

use App\Models\JadwalPembelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DataJadwalPembelajaranImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var array<int, array{line:int,errors:array<int,string>}>
     */
    private array $failed = [];

    public function collection(Collection $rows): void
    {
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            $payload = [
                'kode_kelas' => $this->rowValue($row, 'kode_kelas'),
                'kode_mapel' => $this->rowValue($row, 'kode_mapel'),
                'tahun_ajaran' => $this->rowValue($row, 'tahun_ajaran'),
                'hari' => $this->rowValue($row, 'hari'),
                'jam_mulai' => $this->rowValue($row, 'jam_mulai'),
                'jam_selesai' => $this->rowValue($row, 'jam_selesai'),
                'ruangan' => $this->rowValue($row, 'ruangan'),
                'status' => $this->rowValue($row, 'status'),
            ];

            if ($this->isEmptyRow($payload)) {
                continue;
            }

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
                    $this->failed[] = [
                        'line' => $lineNumber,
                        'errors' => ["Kelas Mapel dengan Kode Kelas '{$payload['kode_kelas']}' dan Kode Mapel '{$payload['kode_mapel']}' tidak ditemukan atau tidak aktif."],
                    ];
                    continue;
                }
            } else {
                $this->failed[] = [
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
                $this->failed[] = [
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
                $this->updated++;
                continue;
            }

            JadwalPembelajaran::create($payload);
            $this->inserted++;
        }
    }

    /**
     * @return array{inserted:int,updated:int,failed:int,error_rows:array<int,array{line:int,errors:array<int,string>}>}
     */
    public function result(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'failed' => count($this->failed),
            'error_rows' => $this->failed,
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

    private function rowValue(mixed $row, string $key): mixed
    {
        $value = data_get($row, $key);

        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        return $value;
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
}
