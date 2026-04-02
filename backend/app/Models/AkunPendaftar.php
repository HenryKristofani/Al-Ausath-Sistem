<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AkunPendaftar extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'akun_pendaftar';
    protected $primaryKey = 'id_akun';

        protected $fillable = [
        'nama',
        'email',
        'phone',
        'password_hash',
    ];

    protected $hidden = ['password_hash'];

    public function pendaftaran()
    {
        return $this->hasMany(PpdbPendaftar::class, 'id_akun', 'id_akun');
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}


