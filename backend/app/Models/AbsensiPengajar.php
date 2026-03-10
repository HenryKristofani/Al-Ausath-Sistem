<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPengajar extends Model
{
    protected $table = 'absensi_pengajar';
    protected $primaryKey = 'id_abs_pengajar';

    const UPDATED_AT = null;
        protected $fillable = [
        'id_petugas',
        'id_sesi',
        'tanggal',
        'status_kehadiran',
        'menit_terlambat',
        'keterangan',
        'input_oleh',
    ];
}


