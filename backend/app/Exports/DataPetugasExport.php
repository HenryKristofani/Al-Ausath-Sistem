<?php

namespace App\Exports;

use App\Models\DataPetugas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataPetugasExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $status,
        private readonly ?string $peranAkun,
        private readonly ?string $keyword,
    ) {
    }

    public function collection(): Collection
    {
        return DataPetugas::query()
            ->when(!empty($this->status), fn ($q) => $q->where('status', strtoupper($this->status)))
            ->when(!empty($this->peranAkun), fn ($q) => $q->whereJsonContains('peran_akun', $this->peranAkun))
            ->when(!empty($this->keyword), function ($q) {
                $keyword = $this->keyword;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('alamat_email', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_petugas')
            ->get([
                'nomor_induk',
                'nama_lengkap',
                'peran_akun',
                'alamat_email',
                'nomor_telepon',
                'status',
                'last_login',
            ])
            ->map(function ($row) {
                $row->peran_akun = is_array($row->peran_akun) ? implode(', ', $row->peran_akun) : $row->peran_akun;
                $row->last_login = optional($row->last_login)->format('Y-m-d H:i:s');
                return $row;
            });
    }

    public function headings(): array
    {
        return [
            'nomor_induk',
            'nama_lengkap',
            'peran_akun',
            'alamat_email',
            'nomor_telepon',
            'status',
            'last_login',
        ];
    }
}
