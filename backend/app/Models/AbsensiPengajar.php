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

    public function petugas()
    {
        return $this->belongsTo(DataPetugas::class, 'id_petugas', 'id_petugas');
    }

    public function inputOlehPetugas()
    {
        return $this->belongsTo(DataPetugas::class, 'input_oleh', 'id_petugas');
    }

    public function sesi()
    {
        return $this->belongsTo(SesiAbsensi::class, 'id_sesi', 'id_sesi');
    }
}


