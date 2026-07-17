<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KkmMapel extends Model
{
    protected $table = 'kkm_mapel';
    protected $primaryKey = 'id_kkm';

    public const GLOBAL_JENJANG = 'GLOBAL';

    const UPDATED_AT = null;

    protected $fillable = [
        'kode_mapel',
        'jenjang',
        'kode_unit',
        'tahun_ajaran',
        'semester',
        'nilai_kkm',
        'status_ketuntasan',
        'keterangan',
    ];

    protected $casts = [
        'semester' => 'integer',
        'nilai_kkm' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $kkm): void {
            $kkm->jenjang = self::GLOBAL_JENJANG;
        });
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(DataMataPelajaran::class, 'kode_mapel', 'kode_mapel');
    }

    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'kode_unit', 'kode_unit');
    }

    public function isTuntas(float $nilaiAkhir): bool
    {
        return $nilaiAkhir >= (float) $this->nilai_kkm;
    }

    public function statusKetuntasan(float $nilaiAkhir): string
    {
        return $this->isTuntas($nilaiAkhir) ? 'TUNTAS' : 'BELUM_TUNTAS';
    }
}
