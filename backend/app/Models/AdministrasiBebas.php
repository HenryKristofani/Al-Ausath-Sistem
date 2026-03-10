<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrasiBebas extends Model
{
    protected $table = 'administrasi_bebas';
    protected $primaryKey = 'id_admin_bebas';

    const UPDATED_AT = null;

        protected $fillable = [
        'id_santri',
        'deskripsi',
        'total_tagihan',
        'sisa',
        'status',
    ];
}


