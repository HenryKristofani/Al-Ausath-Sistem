<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NilaiAkhlak extends Model
{
    protected $table = 'nilai_akhlak';
    protected $primaryKey = 'id_akhlak';

    protected $fillable = [
        'nomor_induk',
        'tahun_ajaran',
        'semester',
        'aspek',
        'nilai_angka',
        'predikat',
        'deskripsi',
        'id_petugas_input',
    ];

    protected $casts = [
        'semester' => 'integer',
        'nilai_angka' => 'decimal:2',
    ];

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'nomor_induk', 'nomor_induk');
    }

    public function petugas()
    {
        return $this->belongsTo(DataPetugas::class, 'id_petugas_input', 'id_petugas');
    }

    public function scopePerSantri(Builder $query, string $nomorInduk, string $tahunAjaran, int $semester): Builder
    {
        return $query
            ->where('nomor_induk', $nomorInduk)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester);
    }
}
