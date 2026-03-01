<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKategoriTagihan extends Model
{
    protected $table = 'data_kategori_tagihan';
    protected $primaryKey = 'id_kategori';

    protected $fillable = [
        'nama_kategori', 'deskripsi', 'status',
    ];
}
