<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbPeriod extends Model
{
    protected $table = 'ppdb_periods';

    protected $fillable = [
        'nama_gelombang',
        'tahun_ajaran',
        'tanggal_mulai',
        'tanggal_selesai',
        'kuota',
        'biaya_pendaftaran',
        'status',
        'deskripsi',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'biaya_pendaftaran' => 'decimal:2',
        'kuota' => 'integer',
    ];

    /**
     * Scope: hanya gelombang aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    /**
     * Scope: gelombang yang sedang berlangsung (aktif + dalam rentang tanggal)
     */
    public function scopeSedangBerlangsung($query)
    {
        return $query->where('status', 'aktif')
            ->where('tanggal_mulai', '<=', now()->toDateString())
            ->where('tanggal_selesai', '>=', now()->toDateString());
    }

    /**
     * Cek apakah gelombang masih menerima pendaftaran.
     */
    public function isBuka(): bool
    {
        return $this->status === 'aktif'
            && now()->startOfDay()->greaterThanOrEqualTo($this->tanggal_mulai->startOfDay())
            && now()->startOfDay()->lessThanOrEqualTo($this->tanggal_selesai->startOfDay());
    }

    /**
     * Jumlah pendaftar yang terdaftar di gelombang ini.
     */
    public function jumlahPendaftar(): int
    {
        return PpdbPendaftar::where('ppdb_period_id', $this->id)->count();
    }

    /**
     * Cek apakah kuota sudah penuh.
     */
    public function isKuotaPenuh(): bool
    {
        if ($this->kuota === null) {
            return false; // Tidak ada batas kuota
        }

        return $this->jumlahPendaftar() >= $this->kuota;
    }

    /**
     * Relasi ke pendaftar PPDB.
     */
    public function pendaftar()
    {
        return $this->hasMany(PpdbPendaftar::class, 'ppdb_period_id');
    }

    /**
     * Relasi ke admin/petugas yang membuat gelombang.
     */
    public function creator()
    {
        return $this->belongsTo(DataPetugas::class, 'created_by', 'id_petugas');
    }
}
