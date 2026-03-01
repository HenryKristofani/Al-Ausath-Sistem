<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KwitansiPdf extends Model
{
    protected $table = 'kwitansi_pdf';
    protected $primaryKey = 'id_kwitansi';

    protected $fillable = [
        'id_pembayaran', 'nama_file', 'path_file',
        'dibuat_oleh', 'tanggal_cetak',
    ];

    protected $casts = [
        'tanggal_cetak' => 'datetime',
    ];
}
