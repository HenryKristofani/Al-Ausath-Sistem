<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbVerifikasi extends Model
{
    protected $table = 'ppdb_verifikasi';
    protected $primaryKey = 'id_verifikasi';

    protected $fillable = [
        'id_pendaftaran', 'id_petugas', 'status_verifikasi',
        'catatan', 'tanggal_verifikasi',
    ];

    protected $casts = [
        'tanggal_verifikasi' => 'datetime',
    ];

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}
