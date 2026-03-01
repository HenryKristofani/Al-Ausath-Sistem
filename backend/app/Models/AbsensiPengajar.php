<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPengajar extends Model
{
    protected $table = 'absensi_pengajar';
    protected $primaryKey = 'id_absensi_pengajar';

    protected $fillable = [
        'id_sesi', 'id_petugas', 'status_kehadiran',
        'keterangan', 'timestamp_input',
    ];

    protected $casts = [
        'timestamp_input' => 'datetime',
    ];
}
