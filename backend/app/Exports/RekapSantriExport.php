<?php

namespace App\Exports;

use App\Http\Controllers\Api\Akademik\RekapAbsensiController;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RekapSantriExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $controller = new RekapAbsensiController();
        $data = $controller->queryRekapSantri($this->request)->get();
        $controller->transformRekapSantri($data);
        return $data;
    }

    public function headings(): array
    {
        return [
            'Nomor Induk',
            'Nama Santri',
            'Kode Kelas',
            'Nama Kelas',
            'Total Pertemuan',
            'Hadir',
            'Izin',
            'Sakit',
            'Alfa',
            'Persentase Kehadiran (%)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nomor_induk,
            $row->nama_lengkap_santri,
            $row->kode_kelas,
            $row->nama_kelas,
            $row->total_pertemuan,
            $row->jumlah_hadir,
            $row->jumlah_izin,
            $row->jumlah_sakit,
            $row->jumlah_alfa,
            $row->persentase_kehadiran,
        ];
    }
}
