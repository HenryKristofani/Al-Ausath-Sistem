<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesiAbsensi extends Model
{
    protected $table = 'sesi_absensi';
    protected $primaryKey = 'id_sesi';

    protected $fillable = [
        'id_jadwal', 'tanggal_sesi', 'status_sesi',
        'dibuka_oleh', 'waktu_buka', 'waktu_tutup',
    ];

    protected $casts = [
        'tanggal_sesi' => 'date',
        'waktu_buka'   => 'datetime',
        'waktu_tutup'  => 'datetime',
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
