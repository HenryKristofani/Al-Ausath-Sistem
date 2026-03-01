<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogPerubahanAbsensi extends Model
{
    protected $table = 'log_perubahan_absensi';
    protected $primaryKey = 'id_log_absensi';
    public $timestamps = false;

    protected $fillable = [
        'id_absensi', 'id_petugas', 'status_lama',
        'status_baru', 'alasan', 'waktu_perubahan',
    ];

    protected $casts = [
        'waktu_perubahan' => 'datetime',
    ];
}
