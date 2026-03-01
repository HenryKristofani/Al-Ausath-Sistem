<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AkunPendaftar extends Model
{
    protected $table = 'akun_pendaftar';
    protected $primaryKey = 'id_akun';

    protected $fillable = [
        'nama_akun', 'alamat_email', 'nomor_telepon', 'status',
    ];

    protected $hidden = ['password_hash'];

    public function pendaftaran()
    {
        return $this->hasMany(PpdbPendaftar::class, 'id_akun', 'id_akun');
    }
}
