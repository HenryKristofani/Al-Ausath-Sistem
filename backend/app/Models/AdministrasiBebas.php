<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrasiBebas extends Model
{
    protected $table = 'administrasi_bebas';
    protected $primaryKey = 'id_admin_bebas';

    // Model only has created_at, no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'id_santri',
        'deskripsi',
        'kategori',
        'tahun_ajaran',
        'total_tagihan',
        'sisa',
        'status',
    ];

    protected $casts = [
        'total_tagihan' => 'decimal:2',
        'sisa' => 'decimal:2',
    ];

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'id_santri', 'id_santri');
    }

    public function pembayaran()
    {
        return $this->hasMany(AdministrasiBebasPembayaran::class, 'id_admin_bebas', 'id_admin_bebas');
    }

    public function kwitansi()
    {
        return $this->hasMany(KwitansiPdf::class, 'id_admin_bebas', 'id_admin_bebas');
    }
}
