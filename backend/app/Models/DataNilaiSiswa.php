<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataNilaiSiswa extends Model
{
    protected $table = 'data_nilai_siswa';
    protected $primaryKey = 'id_nilai';

    protected $fillable = [
        'nomor_induk',
        'kode_mapel',
        'kode_kelas',
        'tahun_ajaran',
        'semester',
        'nilai_harian',
        'nilai_uts',
        'nilai_uas',
        'nilai_akhir_mapel',
        'nilai_rapor_tampil',
        'flag_warna_rapor',
        'keterangan',
        'id_petugas_input',
    ];
}
