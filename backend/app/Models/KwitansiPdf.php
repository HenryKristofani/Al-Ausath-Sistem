<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KwitansiPdf extends Model
{
    protected $table = 'kwitansi_pdf';
    protected $primaryKey = 'id_kwitansi';

    const UPDATED_AT = null;

        protected $fillable = [
        'id_pembayaran',
        'id_petugas',
        'jenis',
        'jumlah',
        'file_path_pdf',
    ];
}


