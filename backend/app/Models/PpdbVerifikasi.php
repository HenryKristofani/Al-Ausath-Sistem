<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbVerifikasi extends Model
{
    protected $table = 'ppdb_verifikasi';
    protected $primaryKey = 'id_verif';

    public $timestamps = false;

        protected $fillable = [
        'id_pendaftaran',
        'id_petugas',
        'tanggal_verif',
        'hasil',
        'catatan',
    ];

        protected $casts = [
        'tanggal_verif' => 'datetime',
    ];

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function petugas()
    {
        return $this->belongsTo(DataPetugas::class, 'id_petugas', 'id_petugas');
    }
}


