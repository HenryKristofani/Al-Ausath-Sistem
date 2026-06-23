<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKelas extends Model
{
    protected $table = 'data_kelas';
    protected $primaryKey = 'id_kelas';

    protected $fillable = [
        'kode_unit',
        'kode_kelas',
        'nama_kelas',
        'nama_jurusan',
        'tahun_ajaran',
        'status',
        'status_ppdb',
        'id_wali_kelas',
            'is_deleted',
            'deleted_at',
    ];

        protected $casts = [
            'is_deleted' => 'boolean',
            'deleted_at' => 'datetime',
        ];

    // Relasi ke DataUnit
    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'kode_unit', 'kode_unit');
    }

    // Relasi ke DataSantri
    public function santri()
    {
        return $this->hasMany(DataSantri::class, 'kode_kelas', 'kode_kelas');
    }

    public function santriAktif()
    {
        return $this->santri()->whereRaw('UPPER(status) = ?', ['AKTIF']);
    }

    public function santriLulus()
    {
        return $this->santri()->whereRaw('UPPER(status) = ?', ['LULUS']);
    }

    public function santriKeluar()
    {
        return $this->santri()->whereRaw('UPPER(status) = ?', ['KELUAR']);
    }

    // Relasi ke DataTahunAjaran
    public function tahunAjaranRelasi()
    {
        return $this->belongsTo(DataTahunAjaran::class, 'tahun_ajaran', 'kode_tahun');
    }

    // Relasi ke DataPetugas (wali kelas)
    public function waliKelas()
    {
        return $this->belongsTo(DataPetugas::class, 'id_wali_kelas', 'id_petugas');
    }
}


