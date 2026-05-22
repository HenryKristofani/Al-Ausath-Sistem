<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BobotNilai extends Model
{
    protected $table = 'bobot_nilai';
    protected $primaryKey = 'id_bobot';

    public const GLOBAL_JENJANG = 'GLOBAL';

    protected $fillable = [
        'jenjang',
        'kode_unit',
        'tahun_ajaran',
        'semester',
        'bobot_harian',
        'bobot_uts',
        'bobot_uas',
        'bobot_kehadiran',
    ];

    protected $casts = [
        'semester' => 'integer',
        'bobot_harian' => 'decimal:2',
        'bobot_uts' => 'decimal:2',
        'bobot_uas' => 'decimal:2',
        'bobot_kehadiran' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $bobot): void {
            $bobot->jenjang = self::GLOBAL_JENJANG;
            $bobot->kode_unit = null;
            $bobot->bobot_kehadiran = 0;

            $totalBobot = (float) $bobot->bobot_harian + (float) $bobot->bobot_uts + (float) $bobot->bobot_uas;
            if (round($totalBobot, 2) !== 100.0) {
                throw new InvalidArgumentException('Total bobot nilai harus 100.');
            }
        });
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('jenjang', self::GLOBAL_JENJANG);
    }

    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'kode_unit', 'kode_unit');
    }
}
