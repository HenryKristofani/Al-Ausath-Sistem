<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    protected $fillable = [
        'judul',
        'konten',
        'kategori',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_aktif',
        'is_pinned',
        'urutan',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'is_aktif'        => 'boolean',
        'is_pinned'       => 'boolean',
        'urutan'          => 'integer',
    ];
}
