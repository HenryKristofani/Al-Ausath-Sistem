<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardPresensiController extends Controller
{
    public function overviewHarian(Request $request): JsonResponse
    {
        $tanggal = $request->query('tanggal', date('Y-m-d'));

        // 1. Santri Stats (Active Santri)
        $totalSantri = DB::table('data_santri')->where('status', 'AKTIF')->count();
        
        $santriAbsensi = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->whereDate('s.tanggal', $tanggal)
            ->select('a.nomor_induk', 'a.status_kehadiran')
            ->get();
            
        // Rekap unik per santri pada hari ini
        $santriStatusHarian = [];
        foreach ($santriAbsensi as $abs) {
            $ni = $abs->nomor_induk;
            if (!isset($santriStatusHarian[$ni])) {
                $santriStatusHarian[$ni] = $abs->status_kehadiran;
            } else {
                if ($abs->status_kehadiran == 'HADIR' && $santriStatusHarian[$ni] != 'HADIR') {
                    $santriStatusHarian[$ni] = 'HADIR';
                }
            }
        }
        
        $santriHadir = 0; $santriSakit = 0; $santriIzin = 0; $santriAlfa = 0;
        foreach($santriStatusHarian as $status) {
            if ($status == 'HADIR') $santriHadir++;
            if ($status == 'SAKIT') $santriSakit++;
            if ($status == 'IZIN') $santriIzin++;
            if ($status == 'ALFA') $santriAlfa++;
        }

        // 2. Guru Stats (Active Petugas GURU)
        $totalGuru = DB::table('data_petugas')
            ->where('status', 'AKTIF')
            ->where(function($q) {
                $q->where('peran_akun', 'LIKE', '%GURU%')
                  ->orWhere('peran_akun', 'LIKE', '%Pengajar%');
            })
            ->count();

        
        $guruAbsensi = DB::table('absensi_pengajar as a')
            ->whereDate('a.tanggal', $tanggal)
            ->select('a.id_petugas', 'a.status_kehadiran')
            ->get();
            
        $guruStatusHarian = [];
        foreach ($guruAbsensi as $abs) {
            $id = $abs->id_petugas;
            if (!isset($guruStatusHarian[$id])) {
                $guruStatusHarian[$id] = $abs->status_kehadiran;
            } else {
                if ($abs->status_kehadiran == 'HADIR') $guruStatusHarian[$id] = 'HADIR';
            }
        }
        $guruHadir = 0; $guruSakit = 0; $guruIzin = 0; $guruAlfa = 0;
        foreach($guruStatusHarian as $status) {
            if ($status == 'HADIR') $guruHadir++;
            if ($status == 'SAKIT') $guruSakit++;
            if ($status == 'IZIN') $guruIzin++;
            if ($status == 'ALFA') $guruAlfa++;
        }
        $guruTidakHadir = $guruSakit + $guruIzin + $guruAlfa;

        // 3. Per Kelas (Based on sesi absensi on that day)
        $perKelas = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->join('data_santri as ds', 'a.nomor_induk', '=', 'ds.nomor_induk')
            ->join('data_kelas as k', 'ds.kode_kelas', '=', 'k.kode_kelas')
            ->leftJoin('data_unit as u', 'k.kode_unit', '=', 'u.kode_unit')
            ->whereDate('s.tanggal', $tanggal)
            ->selectRaw("
                k.nama_kelas as kelas,
                u.nama_unit as jenjang,
                COUNT(DISTINCT ds.nomor_induk) as total,
                SUM(CASE WHEN a.status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN a.status_kehadiran = 'SAKIT' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN a.status_kehadiran = 'IZIN' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN a.status_kehadiran = 'ALFA' THEN 1 ELSE 0 END) as alfa
            ")
            ->groupBy('k.nama_kelas', 'u.nama_unit', 'k.kode_kelas')
            ->orderBy('u.nama_unit')
            ->orderBy('k.nama_kelas')
            ->get()->map(function($item) {
                $item->total_entri = $item->hadir + $item->sakit + $item->izin + $item->alfa;
                $item->percentage = $item->total_entri > 0 ? round(($item->hadir / $item->total_entri) * 100, 1) : 0;
                return $item;
            });

        // 4. Per Mapel
        $perMapel = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->join('jadwal_pembelajaran as j', 's.id_jadwal', '=', 'j.id_jadwal')
            ->join('data_kelas_mapel as km', 'j.id_kelas_mapel', '=', 'km.id_kelas_mapel')
            ->join('data_mata_pelajaran as m', 'km.kode_mapel', '=', 'm.kode_mapel')
            ->leftJoin('data_petugas as p', 'km.id_petugas', '=', 'p.id_petugas')
            ->whereDate('s.tanggal', $tanggal)
            ->selectRaw("
                m.nama_mapel as mapel,
                p.nama_lengkap as guru,
                COUNT(DISTINCT s.id_sesi) as sessions,
                COUNT(a.id_absensi) as total_entri,
                SUM(CASE WHEN a.status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) as hadir
            ")
            ->groupBy('m.nama_mapel', 'p.nama_lengkap')
            ->orderBy('m.nama_mapel')
            ->get()->map(function($item) {
                $item->avgHadir = $item->total_entri > 0 ? round(($item->hadir / $item->total_entri) * 100, 1) : 0;
                return $item;
            });

        // 5. Guru List
        $guruList = DB::table('absensi_pengajar as a')
            ->join('data_petugas as p', 'a.id_petugas', '=', 'p.id_petugas')
            ->leftJoin('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->whereDate('a.tanggal', $tanggal)
            ->selectRaw("
                a.id_abs_pengajar as id,
                p.nama_lengkap as nama,
                p.nomor_induk as nip,
                p.peran_akun as jabatan,
                a.status_kehadiran as status,
                a.menit_terlambat,
                a.keterangan,
                COUNT(s.id_sesi) as mapelHariIni
            ")
            ->groupBy('a.id_abs_pengajar', 'p.nama_lengkap', 'p.nomor_induk', 'p.peran_akun', 'a.status_kehadiran', 'a.menit_terlambat', 'a.keterangan')
            ->orderBy('p.nama_lengkap')
            ->get()->map(function($item) {
                $item->jamMasuk = '-';
                if ($item->status == 'HADIR') {
                    $item->jamMasuk = $item->menit_terlambat > 0 ? "+{$item->menit_terlambat}m" : 'Tepat';
                }
                return $item;
            });

        return response()->json([
            'summary' => [
                'santri' => [
                    'total' => $totalSantri,
                    'hadir' => $santriHadir,
                    'sakit' => $santriSakit,
                    'izin' => $santriIzin,
                    'alfa' => $santriAlfa,
                    'percentage' => $totalSantri > 0 ? round(($santriHadir / $totalSantri) * 100, 1) : 0,
                ],
                'guru' => [
                    'total' => $totalGuru,
                    'hadir' => $guruHadir,
                    'tidakHadir' => $guruTidakHadir,
                    'percentage' => $totalGuru > 0 ? round(($guruHadir / $totalGuru) * 100, 1) : 0,
                ]
            ],
            'perKelas' => $perKelas,
            'perMapel' => $perMapel,
            'guru' => $guruList,
        ]);
    }
}
