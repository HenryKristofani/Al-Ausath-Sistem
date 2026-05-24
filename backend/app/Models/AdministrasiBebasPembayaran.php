<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrasiBebasPembayaran extends Model
{
    protected $table = 'administrasi_bebas_pembayaran';
    protected $primaryKey = 'id_bayar_bebas';

    // Model only has created_at, no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'id_admin_bebas',
        'id_petugas',
        'nominal_bayar',
        'tanggal_bayar',
        'metode_bayar',
        'keterangan',
    ];

    protected $casts = [
        'nominal_bayar' => 'decimal:2',
        'tanggal_bayar' => 'datetime',
    ];

    public function administrasiBebas()
    {
        return $this->belongsTo(AdministrasiBebas::class, 'id_admin_bebas', 'id_admin_bebas');
    }

    public function petugas()
    {
        return $this->belongsTo(DataPetugas::class, 'id_petugas', 'id_petugas');
    }
}
