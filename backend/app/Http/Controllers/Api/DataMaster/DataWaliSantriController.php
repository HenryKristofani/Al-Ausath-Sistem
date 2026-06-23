<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataSantri;
use Illuminate\Http\JsonResponse;

class DataWaliSantriController extends Controller
{
    /**
     * Menampilkan nama orang tua santri berdasarkan nomor induk
     */
    public function show(string $nomor_induk): JsonResponse
    {
        $santri = DataSantri::select(
            'id_santri', 
            'nomor_induk',
            'nama_lengkap_santri', 
            'jenis_kelamin', 
            'nama_ayah_kandung', 
            'nama_ibu_kandung', 
            'nama_wali'
        )->where('nomor_induk', $nomor_induk)->first();

        if (!$santri) {
            return response()->json(['message' => 'Data santri tidak ditemukan'], 404);
        }

        $namaOrangTua = null;

        if ($santri->jenis_kelamin === 'L') {
            $namaOrangTua = $santri->nama_ayah_kandung;
        } elseif ($santri->jenis_kelamin === 'P') {
            $namaOrangTua = $santri->nama_ibu_kandung;
        }

        return response()->json([
            'data' => [
                'id_santri' => $santri->id_santri,
                'nomor_induk' => $santri->nomor_induk,
                'nama_lengkap_santri' => $santri->nama_lengkap_santri,
                'jenis_kelamin' => $santri->jenis_kelamin,
                'nama_ayah_kandung' => $santri->nama_ayah_kandung,
                'nama_ibu_kandung' => $santri->nama_ibu_kandung,
                'nama_wali' => $santri->nama_wali,
                'nama_orang_tua_aktif' => $namaOrangTua,
            ]
        ]);
    }
}
