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
        'keseharian_kebersihan',
        'keseharian_kerapian',
        'keseharian_keterampilan',
        'keseharian_kelakuan',
        'keseharian_kerajinan',
        'keseharian_kedisiplinan',
        'keseharian_ketaatan',
        'status_raport',
        'catatan_wali',
        'id_wali_kelas',
        'tanggal_terbit',
        'ekstrakurikuler',
    ];

    protected $casts = [
        'semester' => 'integer',
        'ekstrakurikuler' => 'array',
    ];
}
