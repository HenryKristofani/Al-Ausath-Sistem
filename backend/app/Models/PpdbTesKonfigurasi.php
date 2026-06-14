<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpdbTesKonfigurasi extends Model
{
    use HasFactory;

    protected $table = 'ppdb_tes_konfigurasi';
    protected $primaryKey = 'id_konfigurasi';

    protected $fillable = [
        'jenjang',
        'fitur_soal_aktif',
        'bahasa',
        'is_rtl',
        'soal_tes',
        'form_schema',
    ];

    protected $casts = [
        'fitur_soal_aktif' => 'boolean',
        'is_rtl' => 'boolean',
        'form_schema' => 'json',
    ];
}
