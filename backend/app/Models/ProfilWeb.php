<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfilWeb extends Model
{
    use HasFactory;

    protected $table = 'profil_web';
    protected $primaryKey = 'id_profil';

    protected $fillable = [
        'tipe',
        'nama',
        'lama_pendidikan',
        'visi',
        'misi',
        'sejarah',
        'program_unggulan',
        'fasilitas',
    ];

    protected $casts = [
        'misi' => 'array',
        'program_unggulan' => 'array',
        'fasilitas' => 'array',
    ];
}
