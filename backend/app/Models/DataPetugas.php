<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class DataPetugas extends Authenticatable
{
    use HasApiTokens, Notifiable; 

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

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}