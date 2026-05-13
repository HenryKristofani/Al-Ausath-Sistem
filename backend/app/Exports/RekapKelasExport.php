<?php

namespace App\Exports;

use App\Http\Controllers\Api\Akademik\RekapAbsensiController;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RekapKelasExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $controller = new RekapAbsensiController();
        $data = $controller->queryRekapKelas($this->request)->get();
        $controller->transformRekapKelas($data);
        return $data;
    }

    public function headings(): array
    {
        return [
            'Kode Kelas',
            'Nama Kelas',
            'Total Sesi',
            'Total Santri Tercatat',
            'Total Entri Absensi',
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
            $row->kode_kelas,
            $row->nama_kelas,
            $row->total_sesi,
            $row->total_santri_tercatat,
            $row->total_entri_absensi,
            $row->jumlah_hadir,
            $row->jumlah_izin,
            $row->jumlah_sakit,
            $row->jumlah_alfa,
            $row->persentase_kehadiran,
        ];
    }
}
