<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class DataPetugas extends Authenticatable
{
    use HasApiTokens, Notifiable; 

    public const PERAN_AKUN_OPTIONS = [
        'Petugas Admin',
        'Petugas Tata Usaha',
        'Petugas PPDB',
        'Petugas SPP',
        'Staf Pengajar',
    ];

    protected $table = 'data_petugas';
    protected $primaryKey = 'id_petugas';

        protected $fillable = [
        'nomor_induk',
        'nama_lengkap',
        'peran_akun',
        'pilihan_unit',
        'alamat_email',
        'nomor_telepon',
        'password_hash',
        'status',
        'last_login',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'last_login' => 'datetime',
        'peran_akun' => 'array',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}

