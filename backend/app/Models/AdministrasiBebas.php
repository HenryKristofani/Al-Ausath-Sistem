<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrasiBebas extends Model
{
    protected $table = 'administrasi_bebas';
    protected $primaryKey = 'id_administrasi';

    protected $fillable = [
        'id_santri', 'jenis_administrasi', 'status', 'keterangan',
        'tanggal_pengajuan', 'tanggal_selesai', 'id_petugas',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_selesai'   => 'date',
    ];
}
