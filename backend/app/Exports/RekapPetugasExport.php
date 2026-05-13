<?php

namespace App\Exports;

use App\Http\Controllers\Api\Akademik\RekapAbsensiController;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RekapPetugasExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $controller = new RekapAbsensiController();
        $data = $controller->queryRekapPetugas($this->request)->get();
        $controller->transformRekapPetugas($data);
        return $data;
    }

    public function headings(): array
    {
        return [
            'ID Petugas',
            'Nama Lengkap',
            'Peran Akun',
            'Total Pertemuan',
            'Hadir',
            'Izin',
            'Sakit',
            'Total Menit Terlambat',
            'Rata-rata Menit Terlambat (Hadir)',
            'Persentase Kehadiran (%)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id_petugas,
            $row->nama_lengkap,
            $row->peran_akun,
            $row->total_pertemuan,
            $row->jumlah_hadir,
            $row->jumlah_izin,
            $row->jumlah_sakit,
            $row->total_menit_terlambat,
            $row->rata_menit_terlambat_hadir,
            $row->persentase_kehadiran,
        ];
    }
}
