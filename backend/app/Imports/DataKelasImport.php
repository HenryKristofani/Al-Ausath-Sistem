<?php

namespace App\Imports;

use App\Models\DataKelas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DataKelasImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var array<int, array{line:int,errors:array<int,string>}>
     */
    private array $failed = [];

    /**
     * @var array<int, string>
     */
    private array $affectedKodeKelas = [];

    public function collection(Collection $rows): void
    {
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            $payload = [
                'kode_unit' => $this->rowValue($row, 'kode_unit'),
                'kode_kelas' => $this->rowValue($row, 'kode_kelas'),
                'nama_kelas' => $this->rowValue($row, 'nama_kelas'),
                'nama_jurusan' => $this->rowValue($row, 'nama_jurusan'),
                'tahun_ajaran' => $this->rowValue($row, 'tahun_ajaran'),
                'status' => $this->rowValue($row, 'status'),
                'status_ppdb' => $this->rowValue($row, 'status_ppdb'),
                'id_wali_kelas' => $this->rowValue($row, 'id_wali_kelas'),
            ];

            if ($this->isEmptyRow($payload)) {
                continue;
            }

            $validator = Validator::make($payload, [
                'kode_unit' => ['required', 'string', 'max:10', 'exists:data_unit,kode_unit'],
                'kode_kelas' => ['required', 'string', 'max:10'],
                'nama_kelas' => ['required', 'string', 'max:100'],
                'nama_jurusan' => ['nullable', 'string', 'max:100'],
                'tahun_ajaran' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
                ],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
                'status_ppdb' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
                'id_wali_kelas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            ]);

            if ($validator->fails()) {
                $this->failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $payload['kode_unit'] = strtoupper((string) $payload['kode_unit']);
            $payload['kode_kelas'] = strtoupper((string) $payload['kode_kelas']);
            $payload['is_deleted'] = false;
            $payload['deleted_at'] = null;

            if (!empty($payload['status'])) {
                $payload['status'] = strtoupper((string) $payload['status']);
            }

            if (!empty($payload['status_ppdb'])) {
                $payload['status_ppdb'] = strtoupper((string) $payload['status_ppdb']);
            }

            $existing = DataKelas::where('kode_kelas', (string) $payload['kode_kelas'])
                ->where('is_deleted', false)
                ->first();

            $existingDeleted = DataKelas::where('kode_kelas', (string) $payload['kode_kelas'])
                ->where('is_deleted', true)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $this->markAffectedKodeKelas((string) $payload['kode_kelas']);
                $this->updated++;
                continue;
            }

            if ($existingDeleted) {
                $existingDeleted->update($payload);
                $this->markAffectedKodeKelas((string) $payload['kode_kelas']);
                $this->updated++;
                continue;
            }

            DataKelas::create($payload);
            $this->markAffectedKodeKelas((string) $payload['kode_kelas']);
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

    /**
     * @return array<int, string>
     */
    public function affectedKodeKelas(): array
    {
        return array_values($this->affectedKodeKelas);
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

    private function markAffectedKodeKelas(string $kodeKelas): void
    {
        $kodeKelas = trim($kodeKelas);

        if ($kodeKelas === '') {
            return;
        }

        $this->affectedKodeKelas[$kodeKelas] = $kodeKelas;
    }
}
