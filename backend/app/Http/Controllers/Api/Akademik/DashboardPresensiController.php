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
            
        $santriHadir = 0; $santriSakit = 0; $santriIzin = 0; $santriAlfa = 0;
        foreach ($santriAbsensi as $abs) {
            $status = strtoupper($abs->status_kehadiran);
            if ($status === 'HADIR') $santriHadir++;
            elseif ($status === 'SAKIT') $santriSakit++;
            elseif ($status === 'IZIN') $santriIzin++;
            elseif ($status === 'ALFA' || $status === 'TIDAK HADIR') $santriAlfa++;
        }
        $totalSantriRecords = $santriHadir + $santriSakit + $santriIzin + $santriAlfa;

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
            
        $guruHadir = 0; $guruSakit = 0; $guruIzin = 0; $guruAlfa = 0;
        foreach ($guruAbsensi as $abs) {
            $status = strtoupper($abs->status_kehadiran);
            if ($status === 'HADIR') $guruHadir++;
            elseif ($status === 'SAKIT') $guruSakit++;
            elseif ($status === 'IZIN') $guruIzin++;
            elseif ($status === 'ALFA' || $status === 'TIDAK HADIR') $guruAlfa++;
        }
        $totalGuruRecords = $guruHadir + $guruSakit + $guruIzin + $guruAlfa;
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
                k.kode_kelas,
                k.nama_kelas as kelas,
                u.nama_unit as jenjang,
                COUNT(DISTINCT s.id_sesi) as total_pertemuan,
                COUNT(DISTINCT ds.nomor_induk) as total,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as alfa
            ")
            ->groupBy('k.kode_kelas', 'k.nama_kelas', 'u.nama_unit')
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
                COUNT(DISTINCT s.id_sesi) as total_pertemuan,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa,
                MAX(COALESCE(a.menit_terlambat, 0)) as menit_terlambat
            ")
            ->groupBy('p.id_petugas', 'p.nama_lengkap', 'p.nomor_induk', 'p.peran_akun')
            ->orderBy('p.nama_lengkap')
            ->get()->map(function($item) {
                $total = $item->total_pertemuan;
                $item->persentase_kehadiran = $total > 0 ? round(($item->jumlah_hadir / $total) * 100, 1) : 0;
                // backward-compat: expose dominant status for legacy badge
                if ($item->jumlah_hadir === $total) {
                    $item->status = 'HADIR';
                } elseif ($item->jumlah_hadir > 0) {
                    $item->status = 'HADIR'; // has at least some attendance
                } elseif ($item->jumlah_sakit > 0) {
                    $item->status = 'SAKIT';
                } elseif ($item->jumlah_izin > 0) {
                    $item->status = 'IZIN';
                } else {
                    $item->status = 'ALFA';
                }
                return $item;
            });

        // 6. Trend Kehadiran
        $trendQuery = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 'a.id_sesi', '=', 's.id_sesi')
            ->join('data_santri as ds', 'a.nomor_induk', '=', 'ds.nomor_induk')
            ->join('data_kelas as k', 'ds.kode_kelas', '=', 'k.kode_kelas')
            ->when($useDate, fn($q) => $q->whereBetween('s.tanggal', [$startDate, $endDate]));

        if ($kodeKelas) {
            $trendQuery->where('k.kode_kelas', $kodeKelas);
        } elseif ($kodeUnit) {
            $trendQuery->where('k.kode_unit', $kodeUnit);
        }
        if ($tahunAjaran) {
            $trendQuery->where('k.tahun_ajaran', $tahunAjaran);
        }

        $trendData = $trendQuery->selectRaw("
                s.tanggal,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN UPPER(a.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as alfa,
                COUNT(a.id_absensi) as total_entri
            ")
            ->groupBy('s.tanggal')
            ->orderBy('s.tanggal')
            ->get()->map(function($item) {
                $item->percentage = $item->total_entri > 0 ? round(($item->hadir / $item->total_entri) * 100, 1) : 0;
                return $item;
            });

        return response()->json([
            'summary' => [
                'santri' => [
                    'total_aktif' => $totalSantri,
                    'total' => $totalSantriRecords,
                    'hadir' => $santriHadir,
                    'sakit' => $santriSakit,
                    'izin' => $santriIzin,
                    'alfa' => $santriAlfa,
                    'percentage' => $totalSantriRecords > 0 ? round(($santriHadir / $totalSantriRecords) * 100, 1) : 0,
                ],
                'guru' => [
                    'total_aktif' => $totalGuru,
                    'total' => $totalGuruRecords,
                    'hadir' => $guruHadir,
                    'sakit' => $guruSakit,
                    'izin' => $guruIzin,
                    'alfa' => $guruAlfa,
                    'tidakHadir' => $guruTidakHadir,
                    'percentage' => $totalGuruRecords > 0 ? round(($guruHadir / $totalGuruRecords) * 100, 1) : 0,
                ]
            ],
            'perKelas' => $perKelas,
            'perMapel' => $perMapel,
            'guru' => $guruList,
            'trend' => $trendData,
        ]);
    }
}
