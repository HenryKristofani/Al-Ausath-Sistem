<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbBerkas extends Model
{
    protected $table = 'ppdb_berkas';
    protected $primaryKey = 'id_berkas';

    public $timestamps = false;

        protected $fillable = [
        'id_pendaftaran',
        'jenis_berkas',
        'file_path',
        'uploaded_at',
    ];

        protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}


