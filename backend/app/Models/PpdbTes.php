<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbTes extends Model
{
    protected $table = 'ppdb_tes';
    protected $primaryKey = 'id_tes';

    public $timestamps = false;

        protected $fillable = [
        'id_pendaftaran',
        'nilai',
        'status_tes',
        'metode_tes',
        'soal_tes',
        'catatan',
    ];

        protected $casts = [
        'nilai' => 'decimal:2',
    ];

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}


