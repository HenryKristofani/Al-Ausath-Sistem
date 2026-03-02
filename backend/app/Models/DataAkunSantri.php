<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class DataAkunSantri extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'data_akun_santri';
    protected $primaryKey = 'id_akun_santri';

    protected $fillable = [
        'nomor_induk', 'nama_akun', 'nama_lengkap', 'nama_unit',
        'nama_kelas', 'tahun_ajaran', 'alamat_email',
        'nomor_telepon', 'status',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'last_login' => 'datetime',
    ];

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'nomor_induk', 'nomor_induk');
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}