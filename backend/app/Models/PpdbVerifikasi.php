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

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}


