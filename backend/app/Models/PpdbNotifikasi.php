<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbNotifikasi extends Model
{
    protected $table = 'ppdb_notifikasi';
    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'id_pendaftaran', 'jenis_notifikasi', 'isi_pesan',
        'status_kirim', 'waktu_kirim',
    ];

    protected $casts = [
        'waktu_kirim' => 'datetime',
    ];
}
