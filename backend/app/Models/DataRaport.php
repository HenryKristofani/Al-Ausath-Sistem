<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRaport extends Model
{
    protected $table = 'data_raport';
    protected $primaryKey = 'id_raport';

        protected $fillable = [
        'nomor_induk',
        'kode_kelas',
        'tahun_ajaran',
        'semester',
        'jumlah_nilai',
        'rata_rata',
        'peringkat_kelas',
        'total_siswa_kelas',
        'hadir',
        'sakit',
        'izin',
        'alpha',
        'status_raport',
        'catatan_wali',
        'id_wali_kelas',
        'tanggal_terbit',
    ];
}


