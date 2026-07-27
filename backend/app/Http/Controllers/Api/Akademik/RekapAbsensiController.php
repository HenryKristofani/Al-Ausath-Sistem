<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RekapAbsensiController extends Controller
{
    public function rekapSantri(Request $request): JsonResponse
    {
        $rows = $this->queryRekapSantri($request)->paginate($this->getPerPage($request));
        $this->transformRekapSantri($rows->getCollection());
        return response()->json($rows);
    }

    public function rekapKelas(Request $request): JsonResponse
    {
        $rows = $this->queryRekapKelas($request)->paginate($this->getPerPage($request));
        $this->transformRekapKelas($rows->getCollection());
        return response()->json($rows);
    }

    public function rekapPetugas(Request $request): JsonResponse
    {
        $rows = $this->queryRekapPetugas($request)->paginate($this->getPerPage($request));
        $this->transformRekapPetugas($rows->getCollection());
        return response()->json($rows);
    }

    public function exportSantri(Request $request)
    {
        $format = $request->query('format', 'pdf');
        
        if ($format === 'excel') {
            return Excel::download(new \App\Exports\RekapSantriExport($request), 'rekap_santri_' . now()->format('Ymd_His') . '.xlsx');
        }

        $data = $this->queryRekapSantri($request)->get();
        $this->transformRekapSantri($data);

        $pdf = Pdf::loadView('exports.pdf.rekap-santri', ['data' => $data, 'request' => $request]);
        return $pdf->download('rekap_santri_' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportKelas(Request $request)
    {
        $format = $request->query('format', 'pdf');
        
        if ($format === 'excel') {
            return Excel::download(new \App\Exports\RekapKelasExport($request), 'rekap_kelas_' . now()->format('Ymd_His') . '.xlsx');
        }

        $data = $this->queryRekapKelas($request)->get();
        $this->transformRekapKelas($data);

        $pdf = Pdf::loadView('exports.pdf.rekap-kelas', ['data' => $data, 'request' => $request]);
        return $pdf->download('rekap_kelas_' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportPetugas(Request $request)
    {
        $format = $request->query('format', 'pdf');
        
        if ($format === 'excel') {
            return Excel::download(new \App\Exports\RekapPetugasExport($request), 'rekap_petugas_' . now()->format('Ymd_His') . '.xlsx');
        }

        $data = $this->queryRekapPetugas($request)->get();
        $this->transformRekapPetugas($data);

        $pdf = Pdf::loadView('exports.pdf.rekap-petugas', ['data' => $data, 'request' => $request]);
        return $pdf->download('rekap_petugas_' . now()->format('Ymd_His') . '.pdf');
    }

    private function getPerPage(Request $request): int
    {
        return (int) ($request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']])['per_page'] ?? 20);
    }

    public function queryRekapSantri(Request $request)
    {
        $validated = $request->validate([
            'tanggal_mulai'  => ['nullable', 'date'],
            'tanggal_selesai'=> ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kode_unit'      => ['nullable', 'string', 'max:10'],
            'kode_kelas'     => ['nullable', 'string', 'max:20'],
            'nomor_induk'    => ['nullable', 'string', 'max:20'],
            'id_jadwal'      => ['nullable', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'tahun_ajaran'   => ['nullable', 'string', 'max:20'],
            'semester'       => ['nullable', 'integer'],
            'q'              => ['nullable', 'string'],
        ]);

        return DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 's.id_sesi', '=', 'a.id_sesi')
            ->join('data_santri as ds', 'ds.nomor_induk', '=', 'a.nomor_induk')
            ->leftJoin('jadwal_pembelajaran as j', 'j.id_jadwal', '=', 's.id_jadwal')
            ->leftJoin('data_kelas_mapel as km', 'km.id_kelas_mapel', '=', 'j.id_kelas_mapel')
            ->leftJoin('data_kelas as k', 'k.kode_kelas', '=', 'ds.kode_kelas')
            ->where('s.status_sesi', '!=', 'BATAL')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('s.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('s.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['kode_unit']), fn ($q) => $q->where('k.kode_unit', $validated['kode_unit']))
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('ds.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['nomor_induk']), fn ($q) => $q->where('ds.nomor_induk', $validated['nomor_induk']))
            ->when(!empty($validated['id_jadwal']), fn ($q) => $q->where('s.id_jadwal', (int) $validated['id_jadwal']))
            ->when(!empty($validated['tahun_ajaran']), fn ($q) => $q->where('k.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn ($q) => $q->where('km.semester', (int) $validated['semester']))
            ->when(!empty($validated['q']), function ($q) use ($validated) {
                $keyword = trim((string) $validated['q']);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('ds.nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('ds.nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('k.nama_kelas', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('ds.nomor_induk')
            ->selectRaw('ds.nama_lengkap_santri')
            ->selectRaw('ds.kode_kelas')
            ->selectRaw('k.nama_kelas')
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa")
            ->groupBy('ds.nomor_induk', 'ds.nama_lengkap_santri', 'ds.kode_kelas', 'k.nama_kelas')
            ->orderBy('ds.kode_kelas')
            ->orderBy('ds.nama_lengkap_santri');
    }

    public function transformRekapSantri($collection)
    {
        $collection->transform(function ($item) {
            $total = (int) $item->total_pertemuan;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            return $item;
        });
    }

    public function rekapMapelSantri(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_mulai'  => ['nullable', 'date'],
            'tanggal_selesai'=> ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'nomor_induk'    => ['required', 'string', 'max:20'],
            'tahun_ajaran'   => ['nullable', 'string', 'max:20'],
            'semester'       => ['nullable', 'integer'],
        ]);

        $rows = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 's.id_sesi', '=', 'a.id_sesi')
            ->join('data_santri as ds', 'ds.nomor_induk', '=', 'a.nomor_induk')
            ->leftJoin('jadwal_pembelajaran as j', 'j.id_jadwal', '=', 's.id_jadwal')
            ->leftJoin('data_kelas_mapel as km', 'km.id_kelas_mapel', '=', 'j.id_kelas_mapel')
            ->leftJoin('data_mata_pelajaran as m', 'm.kode_mapel', '=', 'km.kode_mapel')
            ->where('s.status_sesi', '!=', 'BATAL')
            ->where('ds.nomor_induk', $validated['nomor_induk'])
            ->whereNotNull('m.nama_mapel')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('s.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('s.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['tahun_ajaran']), fn ($q) => $q->where('km.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['semester']), fn ($q) => $q->where('km.semester', (int) $validated['semester']))
            ->selectRaw('m.nama_mapel')
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa")
            ->groupBy('m.nama_mapel')
            ->orderBy('m.nama_mapel')
            ->get();

        $rows->transform(function ($item) {
            $total = (int) $item->total_pertemuan;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            return $item;
        });

        return response()->json(['data' => $rows]);
    }

    public function queryRekapKelas(Request $request)
    {
        $validated = $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kode_kelas' => ['nullable', 'string', 'max:20'],
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'q' => ['nullable', 'string'],
        ]);

        return DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 's.id_sesi', '=', 'a.id_sesi')
            ->join('data_santri as ds', 'ds.nomor_induk', '=', 'a.nomor_induk')
            ->leftJoin('data_kelas as k', 'k.kode_kelas', '=', 'ds.kode_kelas')
            ->where('s.status_sesi', '!=', 'BATAL')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('s.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('s.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('ds.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['id_jadwal']), fn ($q) => $q->where('s.id_jadwal', (int) $validated['id_jadwal']))
            ->when(!empty($validated['q']), function ($q) use ($validated) {
                $keyword = trim((string) $validated['q']);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('ds.kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('k.nama_kelas', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('ds.kode_kelas')
            ->selectRaw('k.nama_kelas')
            ->selectRaw('COUNT(*) as total_entri_absensi')
            ->selectRaw('COUNT(DISTINCT s.id_sesi) as total_sesi')
            ->selectRaw('COUNT(DISTINCT ds.nomor_induk) as total_santri_tercatat')
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw("SUM(CASE WHEN UPPER(a.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa")
            ->groupBy('ds.kode_kelas', 'k.nama_kelas')
            ->orderBy('ds.kode_kelas');
    }

    public function transformRekapKelas($collection)
    {
        $collection->transform(function ($item) {
            $total = (int) $item->total_entri_absensi;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            return $item;
        });
    }

    public function queryRekapPetugas(Request $request)
    {
        $validated = $request->validate([
            'tanggal_mulai'  => ['nullable', 'date'],
            'tanggal_selesai'=> ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'id_petugas'     => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'kode_kelas'     => ['nullable', 'string', 'max:20'],
            'kode_unit'      => ['nullable', 'string', 'max:10'],
            'id_jadwal'      => ['nullable', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'tahun_ajaran'   => ['nullable', 'string', 'max:20'],
            'q'              => ['nullable', 'string'],
        ]);

        return DB::table('absensi_pengajar as ap')
            ->join('data_petugas as p', 'p.id_petugas', '=', 'ap.id_petugas')
            ->leftJoin('sesi_absensi as s', 's.id_sesi', '=', 'ap.id_sesi')
            ->leftJoin('jadwal_pembelajaran as j', 'j.id_jadwal', '=', 's.id_jadwal')
            ->leftJoin('data_kelas_mapel as km', 'km.id_kelas_mapel', '=', 'j.id_kelas_mapel')
            ->leftJoin('data_kelas as k', 'k.kode_kelas', '=', 'km.kode_kelas')
            ->where('s.status_sesi', '!=', 'BATAL')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('ap.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('ap.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['id_petugas']), fn ($q) => $q->where('ap.id_petugas', (int) $validated['id_petugas']))
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('km.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['kode_unit']), fn ($q) => $q->where('k.kode_unit', $validated['kode_unit']))
            ->when(!empty($validated['id_jadwal']), fn ($q) => $q->where('s.id_jadwal', (int) $validated['id_jadwal']))
            ->when(!empty($validated['tahun_ajaran']), fn ($q) => $q->where('km.tahun_ajaran', $validated['tahun_ajaran']))
            ->when(!empty($validated['q']), function ($q) use ($validated) {
                $keyword = trim((string) $validated['q']);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('p.nama_lengkap', 'like', "%{$keyword}%")
                        ->orWhere('p.peran_akun', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('ap.id_petugas')
            ->selectRaw('p.nama_lengkap')
            ->selectRaw('p.peran_akun')
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN UPPER(ap.status_kehadiran) = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN UPPER(ap.status_kehadiran) = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN UPPER(ap.status_kehadiran) = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw("SUM(CASE WHEN UPPER(ap.status_kehadiran) = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa")
            ->selectRaw('SUM(COALESCE(ap.menit_terlambat, 0)) as total_menit_terlambat')
            ->selectRaw("AVG(CASE WHEN UPPER(ap.status_kehadiran) = 'HADIR' THEN COALESCE(ap.menit_terlambat, 0) END) as rata_menit_terlambat_hadir")
            ->groupBy('ap.id_petugas', 'p.nama_lengkap', 'p.peran_akun')
            ->orderBy('p.nama_lengkap');
    }

    public function transformRekapPetugas($collection)
    {
        $collection->transform(function ($item) {
            $total = (int) $item->total_pertemuan;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            $item->rata_menit_terlambat_hadir = $item->rata_menit_terlambat_hadir !== null
                ? round((float) $item->rata_menit_terlambat_hadir, 2)
                : 0;
            return $item;
        });
    }
}
