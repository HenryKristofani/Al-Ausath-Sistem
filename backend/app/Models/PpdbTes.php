<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbTes extends Model
{
    protected $table = 'ppdb_tes';
    protected $primaryKey = 'id_tes';

    protected $fillable = [
        'id_pendaftaran', 'jenis_tes', 'nilai',
        'tanggal_tes', 'keterangan', 'status',
    ];

    protected $casts = [
        'tanggal_tes' => 'date',
        'nilai'       => 'decimal:2',
    ];

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}
