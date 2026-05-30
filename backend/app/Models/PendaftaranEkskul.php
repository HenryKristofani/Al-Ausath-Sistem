<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendaftaranEkskul extends Model
{
    protected $table = 'pendaftaran_ekskul';
    protected $primaryKey = 'id_pendaftaran';

    protected $fillable = [
        'id_santri',
        'id_ekskul',
    ];

    public function ekskul()
    {
        return $this->belongsTo(DataEkskul::class, 'id_ekskul', 'id_ekskul');
    }

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'id_santri', 'id_santri');
    }
}
