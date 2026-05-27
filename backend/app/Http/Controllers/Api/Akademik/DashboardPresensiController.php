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
        $tanggal = $request->query('tanggal');
        $periode = $request->query('periode', 'semua');
        $kodeUnit = $request->query('kode_unit');
        $kodeKelas = $request->query('kode_kelas');

        // Resolve tahun ajaran filter — null means no filter (all years)
        $tahunAjaran = $request->query('tahun_ajaran');
        if ($tahunAjaran === 'ALL' || !$tahunAjaran) {
            $tahunAjaran = null;
        }

        // Calculate date ranges
        $startDate = null;
        $endDate = null;
        $useDate = !empty($tanggal) && $periode !== 'semua';

        if ($useDate) {
            $startDate = $tanggal;
            $endDate = $tanggal;

            if ($periode === 'mingguan') {
                $startDate = date('Y-m-d', strtotime('monday this week', strtotime($tanggal)));
                $endDate   = date('Y-m-d', strtotime('sunday this week',  strtotime($tanggal)));
            } elseif ($periode === 'bulanan') {
                $startDate = date('Y-m-01', strtotime($tanggal));
                $endDate   = date('Y-m-t',  strtotime($tanggal));
            }
        }

        // 1. Santri Stats
        $totalSantriQuery = DB::table('data_santri')->where('status', 'AKTIF');
        if ($kodeKelas) {
            $totalSantriQuery->where('kode_kelas', $kodeKelas);
        } elseif ($kodeUnit) {
            $totalSantriQuery->whereExists(function($q) use ($kodeUnit) {
                $q->select(DB::raw(1))
                  ->from('data_kelas')
                  ->whereColumn('data_kelas.kode_kelas', 'data_santri.kode_kelas')
                  ->where('data_kelas.kode_unit', $kodeUnit);
            });
        }
        if ($tahunAjaran) {
            $totalSantriQuery->whereExists(function($q) use ($tahunAjaran) {
                $q->select(DB::raw(1))
                  ->from('data_kelas')
                  ->whereColumn('data_kelas.kode_kelas', 'data_santri.kode_kelas')
                  ->where('data_kelas.tahun_ajaran', $tahunAjaran);
            });
        }
        $totalSantri = $totalSantriQuery->count();
        
        $santriAbsensiQuery = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->join('data_santri as ds', 'a.nomor_induk', '=', 'ds.nomor_induk')
            ->when($useDate, fn($q) => $q->whereBetween('s.tanggal', [$startDate, $endDate]));
            
        if ($kodeKelas || $kodeUnit || $tahunAjaran) {
            $santriAbsensiQuery->join('data_kelas as k', 'ds.kode_kelas', '=', 'k.kode_kelas');
        }
        if ($kodeKelas) {
            $santriAbsensiQuery->where('ds.kode_kelas', $kodeKelas);
        }
        if ($kodeUnit) {
            $santriAbsensiQuery->where('k.kode_unit', $kodeUnit);
        }
        if ($tahunAjaran) {
            $santriAbsensiQuery->where('k.tahun_ajaran', $tahunAjaran);
        }
        
        $santriAbsensi = $santriAbsensiQuery->select('a.nomor_induk', 'a.status_kehadiran')->get();
            
        // Rekap unik per santri pada periode
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

        // 2. Guru Stats
        $totalGuruQuery = DB::table('data_petugas')
            ->where('status', 'AKTIF')
            ->where(function($q) {
                $q->where('peran_akun', 'LIKE', '%GURU%')
                  ->orWhere('peran_akun', 'LIKE', '%Pengajar%');
            });

        if ($kodeKelas || $kodeUnit || $tahunAjaran) {
            $totalGuruQuery->whereExists(function($q) use ($kodeKelas, $kodeUnit, $tahunAjaran) {
                $q->select(DB::raw(1))
                  ->from('data_kelas_mapel')
                  ->whereColumn('data_kelas_mapel.id_petugas', 'data_petugas.id_petugas');
                  
                if ($kodeKelas) {
                    $q->where('data_kelas_mapel.kode_kelas', $kodeKelas);
                } elseif ($kodeUnit) {
                    $q->join('data_kelas', 'data_kelas_mapel.kode_kelas', '=', 'data_kelas.kode_kelas')
                      ->where('data_kelas.kode_unit', $kodeUnit);
                }
                
                if ($tahunAjaran) {
                    $q->where('data_kelas_mapel.tahun_ajaran', $tahunAjaran);
                }
            });
        }
        $totalGuru = $totalGuruQuery->count();

        $guruAbsensiQuery = DB::table('absensi_pengajar as a')
            ->join('data_petugas as p', 'a.id_petugas', '=', 'p.id_petugas')
            ->when($useDate, fn($q) => $q->whereBetween('a.tanggal', [$startDate, $endDate]));

        if ($kodeKelas || $kodeUnit || $tahunAjaran) {
            $guruAbsensiQuery->whereExists(function($q) use ($kodeKelas, $kodeUnit, $tahunAjaran) {
                $q->select(DB::raw(1))
                  ->from('data_kelas_mapel')
                  ->whereColumn('data_kelas_mapel.id_petugas', 'p.id_petugas');
                  
                if ($kodeKelas) {
                    $q->where('data_kelas_mapel.kode_kelas', $kodeKelas);
                } elseif ($kodeUnit) {
                    $q->join('data_kelas', 'data_kelas_mapel.kode_kelas', '=', 'data_kelas.kode_kelas')
                      ->where('data_kelas.kode_unit', $kodeUnit);
                }
                
                if ($tahunAjaran) {
                    $q->where('data_kelas_mapel.tahun_ajaran', $tahunAjaran);
                }
            });
        }
        
        $guruAbsensi = $guruAbsensiQuery->select('a.id_petugas', 'a.status_kehadiran')->get();
            
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

        // 3. Per Kelas (Based on sesi absensi in date range)
        $perKelasQuery = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->join('data_santri as ds', 'a.nomor_induk', '=', 'ds.nomor_induk')
            ->join('data_kelas as k', 'ds.kode_kelas', '=', 'k.kode_kelas')
            ->leftJoin('data_unit as u', 'k.kode_unit', '=', 'u.kode_unit')
            ->when($useDate, fn($q) => $q->whereBetween('s.tanggal', [$startDate, $endDate]));

        if ($kodeKelas) {
            $perKelasQuery->where('k.kode_kelas', $kodeKelas);
        } elseif ($kodeUnit) {
            $perKelasQuery->where('k.kode_unit', $kodeUnit);
        }
        if ($tahunAjaran) {
            $perKelasQuery->where('k.tahun_ajaran', $tahunAjaran);
        }

        $perKelas = $perKelasQuery->selectRaw("
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
        $perMapelQuery = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->join('jadwal_pembelajaran as j', 's.id_jadwal', '=', 'j.id_jadwal')
            ->join('data_kelas_mapel as km', 'j.id_kelas_mapel', '=', 'km.id_kelas_mapel')
            ->join('data_mata_pelajaran as m', 'km.kode_mapel', '=', 'm.kode_mapel')
            ->leftJoin('data_petugas as p', 'km.id_petugas', '=', 'p.id_petugas')
            ->when($useDate, fn($q) => $q->whereBetween('s.tanggal', [$startDate, $endDate]));

        if ($kodeKelas) {
            $perMapelQuery->where('km.kode_kelas', $kodeKelas);
        } elseif ($kodeUnit) {
            $perMapelQuery->join('data_kelas as k', 'km.kode_kelas', '=', 'k.kode_kelas')
                ->where('k.kode_unit', $kodeUnit);
        }
        if ($tahunAjaran) {
            $perMapelQuery->where('km.tahun_ajaran', $tahunAjaran);
        }

        $perMapel = $perMapelQuery->selectRaw("
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
        $guruListQuery = DB::table('absensi_pengajar as a')
            ->join('data_petugas as p', 'a.id_petugas', '=', 'p.id_petugas')
            ->leftJoin('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->leftJoin('jadwal_pembelajaran as jl', 's.id_jadwal', '=', 'jl.id_jadwal')
            ->leftJoin('data_kelas_mapel as km', 'jl.id_kelas_mapel', '=', 'km.id_kelas_mapel')
            ->when($useDate, fn($q) => $q->whereBetween('a.tanggal', [$startDate, $endDate]));

        if ($kodeKelas) {
            $guruListQuery->where('km.kode_kelas', $kodeKelas);
        } elseif ($kodeUnit) {
            $guruListQuery->join('data_kelas as kl', 'km.kode_kelas', '=', 'kl.kode_kelas')
                ->where('kl.kode_unit', $kodeUnit);
        }

        if ($tahunAjaran) {
            $guruListQuery->where('km.tahun_ajaran', $tahunAjaran);
        }

        $guruList = $guruListQuery->selectRaw("
                p.id_petugas as id,
                p.nama_lengkap as nama,
                p.nomor_induk as nip,
                p.peran_akun as jabatan,
                MAX(a.status_kehadiran) as status,
                MAX(COALESCE(a.menit_terlambat, 0)) as menit_terlambat,
                MAX(a.keterangan) as keterangan,
                COUNT(DISTINCT s.id_sesi) as mapelHariIni
            ")
            ->groupBy('p.id_petugas', 'p.nama_lengkap', 'p.nomor_induk', 'p.peran_akun')
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
