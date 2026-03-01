<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataPetugas extends Model
{
    protected $table = 'data_petugas';
    protected $primaryKey = 'id_petugas';

    protected $fillable = [
        'nomor_induk', 'nama_lengkap', 'peran_akun', 'pilihan_unit',
        'alamat_email', 'nomor_telepon', 'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'last_login' => 'datetime',
    ];
}
