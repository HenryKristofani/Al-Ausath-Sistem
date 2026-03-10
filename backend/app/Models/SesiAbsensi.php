<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesiAbsensi extends Model
{
    protected $table = 'sesi_absensi';
    protected $primaryKey = 'id_sesi';

    const UPDATED_AT = null;

        protected $fillable = [
        'id_jadwal',
        'id_petugas_hadir',
        'id_petugas_pengganti',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'status_sesi',
        'keterangan',
        'is_validated',
        'validated_by',
        'validated_at',
    ];

    public function jadwal()
    {
        return $this->belongsTo(JadwalPembelajaran::class, 'id_jadwal', 'id_jadwal');
    }

    public function absensiSantri()
    {
        return $this->hasMany(AbsensiSantri::class, 'id_sesi', 'id_sesi');
    }
}


