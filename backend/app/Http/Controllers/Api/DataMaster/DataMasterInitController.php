<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataKelas;
use App\Models\DataPetugas;
use App\Models\DataTahunAjaran;
use App\Models\DataUnit;
use App\Models\DataMataPelajaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataMasterInitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Endpoint sentral untuk mengambil data opsi filter (dropdown)
     * yang umum digunakan di berbagai halaman Data Master.
     * Mengurangi jumlah request (waterfall) di sisi frontend.
     *
     * GET /api/data-master/init
     */
    public function initOptions(Request $request): JsonResponse
    {
        // 1. Semua Unit (termasuk nonaktif agar bisa muncul di filter)
        $unit = DataUnit::orderBy('nama_unit')
            ->get(['kode_unit', 'nama_unit', 'status']);

        // 2. Semua Kelas (dengan relasi ke kode_unit)
        $kelas = DataKelas::orderBy('nama_kelas')
            ->get(['kode_kelas', 'nama_kelas', 'kode_unit', 'tahun_ajaran', 'status']);

        // 3. 10 Tahun Ajaran Terakhir
        $tahunAjaran = DataTahunAjaran::orderByDesc('kode_tahun')
            ->limit(10)
            ->get(['kode_tahun', 'nama_tahun', 'status']);

        // 4. Peran Petugas (Distinct)
        // Jika ada tabel/enum terpisah, bisa diambil dari situ.
        // Di sini kita ambil nilai unik dari DataPetugas
        $peran = DataPetugas::select('peran_akun')
            ->distinct()
            ->whereNotNull('peran_akun')
            ->pluck('peran_akun');
            
        // Jika tidak ada di DB (misal saat awal belum ada petugas), 
        // fallback ke opsi default yang ada di frontend
        if ($peran->isEmpty()) {
            $peran = ["Petugas Admin", "Petugas Tata Usaha", "Petugas PPDB", "Petugas SPP", "Staf Pengajar"];
        }

        // 5. Opsi Tambahan: Mata Pelajaran (untuk halaman mapel / jadwal)
        $mapel = DataMataPelajaran::orderBy('nama_mapel')
            ->get(['kode_mapel', 'nama_mapel', 'kode_unit', 'kelompok_mapel', 'status']);

        // 6. Daftar Petugas (untuk dropdown guru/petugas)
        $petugasList = DataPetugas::orderBy('nama_lengkap')
            ->get(['id_petugas', 'nama_lengkap', 'status']);

        return response()->json([
            'unit'         => $unit,
            'kelas'        => $kelas,
            'tahun_ajaran' => $tahunAjaran,
            'peran'        => $peran,
            'mapel'        => $mapel,
            'petugas_list' => $petugasList,
        ]);
    }
}
