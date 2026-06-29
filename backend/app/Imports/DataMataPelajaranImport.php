<?php

namespace App\Imports;

use App\Models\DataMataPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DataMataPelajaranImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
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
                'kode_mapel' => $this->rowValue($row, 'kode_mapel'),
                'nama_mapel' => $this->rowValue($row, 'nama_mapel'),
                'kode_unit' => $this->rowValue($row, 'kode_unit'),
                'kelompok_mapel' => $this->rowValue($row, 'kelompok_mapel'),
                'keterangan' => $this->rowValue($row, 'keterangan'),
                'status' => $this->rowValue($row, 'status'),
            ];

            if ($this->isEmptyRow($payload)) {
                continue;
            }

            $payload = $this->normalizeMapelInput($payload);

            $validator = Validator::make($payload, [
                'kode_mapel' => ['required', 'string', 'max:20'],
                'nama_mapel' => ['required', 'string', 'max:200'],
                'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
                'kelompok_mapel' => ['nullable', 'string', 'max:50'],
                'keterangan' => ['nullable', 'string'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $this->failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = DataMataPelajaran::where('kode_mapel', (string) $payload['kode_mapel'])->first();

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
                continue;
            }

            DataMataPelajaran::create($payload);
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

    private function normalizeMapelInput(array $payload): array
    {
        foreach (['kode_mapel', 'kode_unit', 'status'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = strtoupper(trim($payload[$field]));
            }
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
