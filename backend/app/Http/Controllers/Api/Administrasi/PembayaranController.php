<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataSantri;
use App\Models\PpdbPendaftar;
use App\Models\PembayaranSpp;
use App\Models\SppSetting;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PembayaranController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Rekap pembayaran terpadu untuk menu Administrasi > Pembayaran.
     * Sumber data: pembayaran_spp (PPDB + SPP).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $statusFilter = $request->filled('status')
            ? $this->normalizeStatusForStorage((string) $request->status)
            : null;

        $query = PembayaranSpp::query()
            ->with(['santri', 'pendaftarPpdb', 'setting', 'rekening', 'kwitansi'])
            ->when($statusFilter !== null, fn ($q) => $q->where('status', $statusFilter))
            ->when($request->filled('jenis_pembayaran'), function ($q) use ($request) {
                $jenis = strtolower(trim((string) $request->jenis_pembayaran));

                if ($jenis === 'ppdb') {
                    $q->whereNotNull('id_pendaftaran');
                }

                if ($jenis === 'spp') {
                    $q->whereNull('id_pendaftaran');
                }
            })
            ->when($request->filled('tanggal_mulai'), fn ($q) => $q->whereDate('tanggal_bayar', '>=', $request->tanggal_mulai))
            ->when($request->filled('tanggal_selesai'), fn ($q) => $q->whereDate('tanggal_bayar', '<=', $request->tanggal_selesai))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->q);

                $q->where(function ($sub) use ($keyword) {
                    $sub->where('metode_bayar', 'like', "%{$keyword}%")
                        ->orWhereHas('santri', function ($santriQuery) use ($keyword) {
                            $santriQuery->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                                ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('pendaftarPpdb', function ($pendaftarQuery) use ($keyword) {
                            $pendaftarQuery->where('nama_calon', 'like', "%{$keyword}%")
                                ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                                ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('id_pembayaran');

        $rows = $query->paginate($perPage);

        $rows->getCollection()->transform(function (PembayaranSpp $row) {
            $isPpdb = !empty($row->id_pendaftaran);

            return [
                'id_pembayaran' => $row->id_pembayaran,
                'nomor_invoice' => $this->buildNomorInvoice($row->id_pembayaran),
                'jenis_pembayaran' => $isPpdb ? 'ppdb' : 'spp',
                'status' => $row->status,
                'status_key' => $this->normalizeStatusForFrontend((string) $row->status),
                'status_label' => $this->buildStatusLabel((string) $row->status),
                'nominal_bayar' => (float) ($row->nominal_bayar ?? 0),
                'tanggal_bayar' => optional($row->tanggal_bayar)->format('Y-m-d H:i:s'),
                'tanggal_verifikasi' => optional($row->tanggal_verifikasi)->format('Y-m-d H:i:s'),
                'metode_bayar' => $row->metode_bayar,
                'pendaftar' => $row->pendaftarPpdb ? [
                    'id_pendaftaran' => $row->pendaftarPpdb->id_pendaftaran,
                    'nama_calon' => $row->pendaftarPpdb->nama_calon,
                    'no_pendaftaran' => $row->pendaftarPpdb->no_pendaftaran_final ?: $row->pendaftarPpdb->no_pendaftaran,
                    'status_verifikasi' => $row->pendaftarPpdb->status_verifikasi,
                ] : null,
                'santri' => $row->santri ? [
                    'id_santri' => $row->santri->id_santri,
                    'nomor_induk' => $row->santri->nomor_induk,
                    'nama_santri' => $row->santri->nama_lengkap_santri,
                    'kode_kelas' => $row->santri->kode_kelas,
                ] : null,
                'kwitansi' => $row->kwitansi ? [
                    'id_kwitansi' => $row->kwitansi->id_kwitansi,
                    'file_path_pdf' => $row->kwitansi->file_path_pdf,
                    'file_url_pdf' => $row->kwitansi->file_path_pdf ? Storage::url($row->kwitansi->file_path_pdf) : null,
                    'jumlah' => $row->kwitansi->jumlah,
                ] : null,
            ];
        });

        return response()->json($rows);
    }

    /**
     * Daftar TAGIHAN untuk halaman khusus tagihan.
     */
    public function tagihan(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->query('page', 1));
        $keyword = mb_strtolower(trim((string) $request->query('q')));
        $nomorInduk = trim((string) $request->query('nomor_induk'));

        $statusFilter = $request->filled('status') ? $this->normalizeStatusForFrontend((string) $request->status) : null;
        $sumberFilter = $request->filled('sumber') ? strtolower((string) $request->sumber) : null;

        // 1. Fetch raw stats from PembayaranSpp for extremely fast status calculation
        $stats = \Illuminate\Support\Facades\DB::table('pembayaran_spp')
            ->select('id_santri', 'id_pendaftaran')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('batal', 'ditolak', 'dibatalkan') THEN nominal_bayar ELSE 0 END) as total_tagihan")
            ->selectRaw("SUM(CASE WHEN status IN ('terverifikasi', 'lunas') THEN nominal_bayar ELSE 0 END) as total_dibayar")
            ->selectRaw("MAX(CASE WHEN status IN ('menunggu_konfirmasi', 'menunggu_verifikasi') THEN 1 ELSE 0 END) as has_menunggu")
            ->selectRaw("COUNT(id_pembayaran) as count_invoice")
            ->groupBy('id_santri', 'id_pendaftaran')
            ->get();

        $bebasStatsRaw = \Illuminate\Support\Facades\DB::table('administrasi_bebas')
            ->select('id_santri')
            ->selectRaw("SUM(total_tagihan) as total_tagihan")
            ->selectRaw("SUM(total_tagihan - sisa) as total_dibayar")
            ->selectRaw("SUM(sisa) as total_sisa")
            ->selectRaw("COUNT(id_admin_bebas) as count_invoice")
            ->groupBy('id_santri')
            ->get()
            ->keyBy('id_santri');

        $santriStats = [];
        $ppdbStats = [];
        foreach ($stats as $row) {
            $tagihan = (float) $row->total_tagihan;
            $dibayar = (float) $row->total_dibayar;
            $tunggakan = max($tagihan - $dibayar, 0);

            $status = 'menunggu_pembayaran';
            if ($tagihan > 0) {
                if ($tunggakan <= 0) {
                    $status = 'lunas';
                } elseif ($row->has_menunggu) {
                    $status = 'menunggu_konfirmasi';
                }
            }

            $statData = [
                'tagihan' => $tagihan,
                'dibayar' => $dibayar,
                'tunggakan' => $tunggakan,
                'status' => $status,
                'count' => $row->count_invoice,
            ];

            if ($row->id_santri) {
                $santriStats[$row->id_santri] = $statData;
            } elseif ($row->id_pendaftaran) {
                $ppdbStats[$row->id_pendaftaran] = $statData;
            }
        }

        foreach ($bebasStatsRaw as $idSantri => $bebasRow) {
            if (isset($santriStats[$idSantri])) {
                $santriStats[$idSantri]['tagihan'] += (float) $bebasRow->total_tagihan;
                $santriStats[$idSantri]['dibayar'] += (float) $bebasRow->total_dibayar;
                $santriStats[$idSantri]['tunggakan'] += (float) $bebasRow->total_sisa;
                $santriStats[$idSantri]['count'] += $bebasRow->count_invoice;
                
                if ($santriStats[$idSantri]['tagihan'] > 0) {
                    if ($santriStats[$idSantri]['tunggakan'] <= 0) {
                        $santriStats[$idSantri]['status'] = 'lunas';
                    } else {
                        if ($santriStats[$idSantri]['status'] !== 'menunggu_konfirmasi') {
                            $santriStats[$idSantri]['status'] = 'menunggu_pembayaran';
                        }
                    }
                }
            } else {
                $status = 'menunggu_pembayaran';
                if ((float)$bebasRow->total_tagihan > 0 && (float)$bebasRow->total_sisa <= 0) {
                    $status = 'lunas';
                }
                $santriStats[$idSantri] = [
                    'tagihan' => (float) $bebasRow->total_tagihan,
                    'dibayar' => (float) $bebasRow->total_dibayar,
                    'tunggakan' => (float) $bebasRow->total_sisa,
                    'status' => $status,
                    'count' => $bebasRow->count_invoice,
                ];
            }
        }

        // 2. Fetch base IDs from entities to support search filters
        $santriQuery = DataSantri::query()
            ->select('id_santri', 'nomor_induk', 'nama_lengkap_santri', 'is_anak_guru')
            ->when($nomorInduk, fn ($q) => $q->where('nomor_induk', $nomorInduk))
            ->when($keyword, function ($q) use ($keyword) {
                $q->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                  ->orWhere('nomor_induk', 'like', "%{$keyword}%");
            });

        $ppdbQuery = PpdbPendaftar::query()
            ->select('id_pendaftaran', 'id_santri', 'no_pendaftaran', 'no_pendaftaran_final', 'nomor_induk_generated', 'nama_calon', 'is_anak_guru')
            ->when($nomorInduk, function ($q) use ($nomorInduk) {
                $q->where(function ($sub) use ($nomorInduk) {
                    $sub->where('nomor_induk_generated', $nomorInduk)
                        ->orWhere('no_pendaftaran_final', $nomorInduk)
                        ->orWhere('no_pendaftaran', $nomorInduk);
                });
            })
            ->when($keyword, function ($q) use ($keyword) {
                $q->where('nama_calon', 'like', "%{$keyword}%")
                  ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                  ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%");
            });

        // Filter arrays based on active filters
        $allowedSantri = [];
        if ($sumberFilter === null || $sumberFilter === 'santri') {
            foreach ($santriQuery->get() as $s) {
                $sStat = $santriStats[$s->id_santri] ?? ['tagihan' => 0, 'dibayar' => 0, 'tunggakan' => 0, 'status' => 'menunggu_pembayaran', 'count' => 0];
                if ($statusFilter !== null && $sStat['status'] !== $statusFilter) continue;
                $allowedSantri[] = $s->id_santri;
            }
        }

        $allowedPpdb = [];
        if ($sumberFilter === null || $sumberFilter === 'ppdb') {
            // Get Santri IDs to exclude PPDB that are already Santri
            $existingSantriIds = array_flip($santriQuery->pluck('id_santri')->toArray());
            foreach ($ppdbQuery->get() as $p) {
                if ($p->id_santri && isset($existingSantriIds[$p->id_santri])) continue;
                
                $pStat = $ppdbStats[$p->id_pendaftaran] ?? ['tagihan' => 0, 'dibayar' => 0, 'tunggakan' => 0, 'status' => 'menunggu_pembayaran', 'count' => 0];
                if ($statusFilter !== null && $pStat['status'] !== $statusFilter) continue;
                $allowedPpdb[] = $p->id_pendaftaran;
            }
        }

        // 3. Paginate the IDs
        $totalSantri = count($allowedSantri);
        $totalPpdb = count($allowedPpdb);
        $total = $totalSantri + $totalPpdb;

        $offset = ($page - 1) * $perPage;
        
        $pageSantriIds = array_slice($allowedSantri, $offset, $perPage);
        $pagePpdbIds = [];
        
        $santriCount = count($pageSantriIds);
        if ($santriCount < $perPage) {
            $ppdbOffset = max(0, $offset - $totalSantri);
            $pagePpdbIds = array_slice($allowedPpdb, $ppdbOffset, $perPage - $santriCount);
        }

        // 4. Hydrate ONLY the paginated records
        $santriModels = DataSantri::with(['kelas.unit'])->whereIn('id_santri', $pageSantriIds)->get()->keyBy('id_santri');
        $ppdbModels = PpdbPendaftar::whereIn('id_pendaftaran', $pagePpdbIds)->get()->keyBy('id_pendaftaran');

        $combined = collect();

        foreach ($pageSantriIds as $id) {
            $s = $santriModels->get($id);
            if (!$s) continue;
            $sStat = $santriStats[$id] ?? ['tagihan' => 0, 'dibayar' => 0, 'tunggakan' => 0, 'status' => 'menunggu_pembayaran', 'count' => 0];
            
            $namaUnit = $s->kelas?->unit?->nama_unit ?? $s->kelas?->kode_unit ?? '-';
            $combined->push([
                'id'             => $id,
                'nama_unit'      => $namaUnit,
                'nomor_induk'    => $s->nomor_induk ?? '-',
                'nama_lengkap'   => $s->nama_lengkap_santri ?? '-',
                'kelas_saat_ini' => $s->kelas?->nama_kelas ?? '-',
                'kelas_sekarang' => $s->kelas?->nama_kelas ?? '-',
                'tahun_ajaran'   => $s->kelas?->tahun_ajaran ?? '-',
                'status'         => $sStat['status'],
                'total_tagihan'  => $sStat['tagihan'],
                'total_dibayar'  => $sStat['dibayar'],
                'total_tunggakan' => $sStat['tunggakan'],
                'jumlah_invoice' => $sStat['count'],
                'sumber_data'    => 'master_data_santri',
                'id_santri'      => $id,
                'id_pendaftaran' => null,
                'is_anak_guru'   => (bool) $s->is_anak_guru,
            ]);
        }

        foreach ($pagePpdbIds as $id) {
            $p = $ppdbModels->get($id);
            if (!$p) continue;
            $pStat = $ppdbStats[$id] ?? ['tagihan' => 0, 'dibayar' => 0, 'tunggakan' => 0, 'status' => 'menunggu_pembayaran', 'count' => 0];
            
            $namaUnit = strtoupper((string) ($p->jenjang ?: $p->program_pendaftaran ?: '-'));
            $nomorInduk = $p->nomor_induk_generated ?? $p->no_pendaftaran_final ?? $p->no_pendaftaran ?? '-';
            $combined->push([
                'id'             => $id,
                'nama_unit'      => $namaUnit,
                'nomor_induk'    => $nomorInduk,
                'nama_lengkap'   => $p->nama_calon ?? '-',
                'kelas_saat_ini' => $p->kode_kelas_diterima ?? '-',
                'kelas_sekarang' => $p->kode_kelas_diterima ?? '-',
                'tahun_ajaran'   => '-', // ppdb usually has no tahun ajaran assigned yet in this view
                'status'         => $pStat['status'],
                'total_tagihan'  => $pStat['tagihan'],
                'total_dibayar'  => $pStat['dibayar'],
                'total_tunggakan' => $pStat['tunggakan'],
                'jumlah_invoice' => $pStat['count'],
                'sumber_data'    => 'ppdb',
                'id_santri'      => null,
                'id_pendaftaran' => $id,
                'is_anak_guru'   => (bool) $p->is_anak_guru,
            ]);
        }

        return response()->json([
            'data' => $combined->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max($total, 1) / $perPage),
            ],
        ]);
    }

    /**
     * Helper to transform a person + their payments into a row.
     */
    private function transformGroupToRow($santri, $pendaftar, $items)
    {
        $totalTagihan = (float) $items
            ->reject(fn ($item) => $this->isCanceledStatus((string) $item->status))
            ->sum('nominal_bayar');

        $totalDibayar = (float) $items
            ->filter(fn ($item) => $this->isPaidStatus((string) $item->status))
            ->sum('nominal_bayar');

        $totalTunggakan = max($totalTagihan - $totalDibayar, 0);

        // Calculate a meaningful payment status
        $status = 'menunggu_pembayaran';
        if ($totalTagihan > 0) {
            if ($totalTunggakan <= 0) {
                $status = 'lunas';
            } elseif ($items->contains(fn($i) => $this->normalizeStatusForFrontend($i->status) === 'menunggu_konfirmasi')) {
                $status = 'menunggu_konfirmasi';
            }
        }

        $namaUnit = $santri?->kelas?->unit?->nama_unit
            ?? $santri?->kelas?->kode_unit
            ?? strtoupper((string) ($pendaftar?->jenjang ?: $pendaftar?->program_pendaftaran ?: '-'));

        $nomorInduk = $santri?->nomor_induk
            ?? $pendaftar?->nomor_induk_generated
            ?? $pendaftar?->no_pendaftaran_final
            ?? $pendaftar?->no_pendaftaran
            ?? '-';

        return [
            'id'             => $santri?->id_santri ?? $pendaftar?->id_pendaftaran,
            'nama_unit'      => $namaUnit,
            'nomor_induk'    => $nomorInduk,
            'nama_lengkap'   => $santri?->nama_lengkap_santri ?? $pendaftar?->nama_calon ?? '-',
            'kelas_saat_ini' => $santri?->kelas?->nama_kelas ?? $pendaftar?->kode_kelas_diterima ?? '-',
            'kelas_sekarang' => $santri?->kelas?->nama_kelas ?? $pendaftar?->kode_kelas_diterima ?? '-',
            'tahun_ajaran'   => $santri?->kelas?->tahun_ajaran ?? '-',
            'status'         => $status,
            'total_tagihan'  => $totalTagihan,
            'total_dibayar'  => $totalDibayar,
            'total_tunggakan' => $totalTunggakan,
            'jumlah_invoice' => $items->count(),
            'sumber_data'    => $santri ? 'master_data_santri' : 'ppdb',
            'id_santri'      => $santri?->id_santri,
            'id_pendaftaran' => $pendaftar?->id_pendaftaran,
        ];
    }

    /**
     * Detail tagihan per entitas (santri/ppdb) untuk halaman /spp/{id}.
     */
    public function tagihanDetail(int $id): JsonResponse
    {
        // Resolve the actual entity objects for profil correctly
        $santri = DataSantri::find($id);
        $pendaftar = null;

        if ($santri) {
            $linkedSantriId = $santri->id_santri;
            $isSantri = true;
            $pendaftar = PpdbPendaftar::where('id_santri', $santri->id_santri)->first();
            $linkedPendaftaranId = $pendaftar?->id_pendaftaran;
        } else {
            $pendaftar = PpdbPendaftar::find($id);
            if ($pendaftar) {
                $linkedPendaftaranId = $pendaftar->id_pendaftaran;
                $linkedSantriId = $pendaftar->id_santri;
                $isSantri = !empty($linkedSantriId);
                if ($isSantri) {
                    $santri = DataSantri::find($linkedSantriId);
                }
            } else {
                // Try from payment row
                $row = PembayaranSpp::with(['santri.kelas.unit', 'pendaftarPpdb', 'setting.kategoriTagihan', 'kwitansi'])
                    ->where('id_pembayaran', $id)
                    ->first();
                if ($row) {
                    $santri = $row->santri;
                    $pendaftar = $row->pendaftarPpdb;
                    $linkedSantriId = $row->id_santri;
                    $linkedPendaftaranId = $row->id_pendaftaran;
                    $isSantri = !empty($linkedSantriId);
                } else {
                    abort(404, 'Santri atau Pendaftar tidak ditemukan');
                }
            }
        }

        // Fetch ALL SPP bills for this entity
        $sppItems = PembayaranSpp::query()
            ->with(['setting.kategoriTagihan', 'kwitansi'])
            ->where(function ($q) use ($isSantri, $linkedSantriId, $linkedPendaftaranId) {
                if ($isSantri && !empty($linkedSantriId)) {
                    $q->where('id_santri', $linkedSantriId);
                    if (!empty($linkedPendaftaranId)) {
                        $q->orWhere('id_pendaftaran', $linkedPendaftaranId);
                    }
                } else if (!empty($linkedPendaftaranId)) {
                    $q->where('id_pendaftaran', $linkedPendaftaranId);
                }
            })
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('id_pembayaran')
            ->get();

        // Fetch ALL Infaq PPDB bills from ppdb_tagihan table
        $infaqItems = collect();
        if (!empty($linkedPendaftaranId) || !empty($linkedSantriId)) {
            $infaqItems = DB::table('ppdb_tagihan')
                ->where(function ($q) use ($linkedPendaftaranId, $linkedSantriId) {
                    if (!empty($linkedPendaftaranId)) {
                        $q->where('id_pendaftaran', $linkedPendaftaranId);
                    }
                    if (!empty($linkedSantriId)) {
                        $q->orWhere('id_santri', $linkedSantriId);
                    }
                })
                ->orderByDesc('created_at')
                ->get();
        }

        // Map SPP items to unified format
        $sppInvoices = collect($sppItems->map(fn (PembayaranSpp $item) => [
            'id_pembayaran'   => $item->id_pembayaran,
            'nomor_invoice'   => $this->buildNomorInvoice($item->id_pembayaran),
            'periode_tagihan' => $item->setting?->periode,
            'rincian_tagihan' => $item->bulan 
                ? (($item->setting?->kategoriTagihan?->nama_tagihan ?: $item->setting?->keterangan ?: 'SPP') . ' - ' . $item->bulan) 
                : ($item->setting?->kategoriTagihan?->nama_tagihan ?: $item->setting?->keterangan ?: (empty($item->id_pendaftaran) ? 'Tagihan SPP' : 'Tagihan PPDB')),
            'jenis_tagihan'   => !empty($item->jenis_tagihan)
                ? strtolower($item->jenis_tagihan)
                : (empty($item->id_pendaftaran) ? 'spp' : 'ppdb'),
            'jumlah_tagihan'  => (float) ($item->nominal_bayar ?? 0),
            'jumlah_dibayar'  => $this->isPaidStatus((string) $item->status) ? (float) ($item->nominal_bayar ?? 0) : 0,
            'jumlah_tunggakan' => $this->isPaidStatus((string) $item->status) ? 0 : (float) ($item->nominal_bayar ?? 0),
            'status'          => $item->status,
            'status_key'      => $this->normalizeStatusForFrontend((string) $item->status),
            'status_label'    => $this->buildStatusLabel((string) $item->status),
            'waktu_invoice'   => optional($item->tanggal_bayar)->format('Y-m-d H:i:s'),
            'kwitansi_tersedia' => (bool) $item->kwitansi,
            'kwitansi_url'    => $item->kwitansi?->file_path_pdf ? Storage::url($item->kwitansi->file_path_pdf) : null,
            'bukti_bayar_path' => $item->bukti_bayar_path,
            'bukti_bayar_url'  => $item->bukti_bayar_path ? Storage::url($item->bukti_bayar_path) : null,
            'catatan_bayar'    => $item->catatan_bayar,
            // Tambahan untuk staging payment
            'jumlah_minimum_dp' => !empty($item->id_pendaftaran) 
                ? (float) ($item->nominal_bayar * 0.5) // 50% untuk tagihan PPDB
                : null,
            'bulan' => $item->bulan, // Format YYYY-MM untuk tracking consecutive months
            '_source' => 'pembayaran_spp',
            '_sort_time' => $item->tanggal_bayar?->format('Y-m-d H:i:s') ?? $item->created_at?->format('Y-m-d H:i:s'),
        ]));

        // Map Infaq items to unified format
        $infaqInvoices = collect($infaqItems->map(fn ($item) => $this->normalizePpdbTagihanRow($item)));

        // Fetch ALL Administrasi Bebas bills for this entity
        $bebasItems = collect();
        if (!empty($linkedSantriId)) {
            $bebasItems = \App\Models\AdministrasiBebas::with(['pembayaran', 'kwitansi'])
                ->where('id_santri', $linkedSantriId)
                ->orderByDesc('id_admin_bebas')
                ->get();
        }

        $bebasInvoices = collect($bebasItems->map(fn (\App\Models\AdministrasiBebas $item) => [
            'id_pembayaran'   => $item->id_admin_bebas,
            'nomor_invoice'   => "INV-BEBAS-" . str_pad((string) $item->id_admin_bebas, 5, '0', STR_PAD_LEFT),
            'periode_tagihan' => $item->tahun_ajaran ?? null,
            'rincian_tagihan' => $item->deskripsi ?: 'Administrasi Bebas',
            'jenis_tagihan'   => 'bebas',
            'jumlah_tagihan'  => (float) ($item->total_tagihan ?? 0),
            'jumlah_dibayar'  => (float) (($item->total_tagihan ?? 0) - ($item->sisa ?? 0)),
            'jumlah_tunggakan' => (float) ($item->sisa ?? 0),
            'status'          => strtolower($item->status),
            'status_key'      => strtolower($item->status) === 'lunas' ? 'lunas' : 'belum_lunas',
            'status_label'    => strtolower($item->status) === 'lunas' ? 'Lunas' : 'Belum Lunas',
            'waktu_invoice'   => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
            'kwitansi_tersedia' => (bool) $item->kwitansi->isNotEmpty(),
            'kwitansi_url'    => $item->kwitansi->first()?->file_path_pdf ? Storage::url($item->kwitansi->first()->file_path_pdf) : null,
            'bukti_bayar_path' => null,
            'bukti_bayar_url'  => null,
            'catatan_bayar'    => null,
            'jumlah_minimum_dp' => null,
            'bulan' => null,
            '_source' => 'administrasi_bebas',
            '_sort_time' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
        ]));

        // Merge all invoice arrays and sort by time
        $allInvoices = $sppInvoices->merge($infaqInvoices)->merge($bebasInvoices)
            ->sortByDesc('_sort_time')
            ->map(function ($item) {
                // Remove internal fields before sending to frontend
                unset($item['_source'], $item['_sort_time']);
                return $item;
            })
            ->values();

        $sppSettings = $sppItems
            ->map(fn (PembayaranSpp $item) => $item->setting)
            ->filter()
            ->unique('id_setting')
            ->values()
            ->map(function ($setting) {
                return [
                    'id_setting' => $setting->id_setting,
                    'nama_setting' => $setting->kategoriTagihan?->nama_tagihan ?: $setting->keterangan ?: 'SPP',
                    'periode' => $setting->periode,
                    'jenjang' => $setting->jenjang,
                    'kode_kelas' => $setting->kode_kelas,
                    'jumlah' => (float) ($setting->jumlah ?? 0),
                    'aktif' => (bool) ($setting->aktif ?? false),
                ];
            })
            ->values();

        $ppdbSelection = $pendaftar ? [
            'pilihan_uang_gedung' => $pendaftar->pilihan_uang_gedung ? (int) $pendaftar->pilihan_uang_gedung : null,
            'pilihan_infaq_bulanan' => $pendaftar->pilihan_infaq_bulanan ? (int) $pendaftar->pilihan_infaq_bulanan : null,
            'is_anak_guru' => (bool) $pendaftar->is_anak_guru,
            'status_verifikasi' => $pendaftar->status_verifikasi,
            'batas_bayar_uang_pangkal' => optional($pendaftar->batas_bayar_uang_pangkal)->format('Y-m-d'),
            'batas_bayar_spp' => optional($pendaftar->batas_bayar_spp)->format('Y-m-d'),
        ] : null;

        // Calculate summary from merged data
        $totalTagihan = (float) $allInvoices
            ->reject(fn ($item) => in_array($item['status_key'], ['dibatalkan']))
            ->sum('jumlah_tagihan');

        $totalDibayar = (float) $allInvoices
            ->filter(fn ($item) => $item['status_key'] === 'lunas')
            ->sum('jumlah_dibayar');

        $totalTunggakan = max($totalTagihan - $totalDibayar, 0);

        return response()->json([
            'data' => [
                'profil' => [
                    'id'           => $isSantri ? $linkedSantriId : $linkedPendaftaranId,
                    'sumber'       => $pendaftar ? 'ppdb' : 'santri',
                    'nama_lengkap' => $santri?->nama_lengkap_santri ?? $pendaftar?->nama_calon,
                    'nomor_induk'  => $santri?->nomor_induk
                        ?? $pendaftar?->nomor_induk_generated
                        ?? $pendaftar?->no_pendaftaran_final
                        ?? $pendaftar?->no_pendaftaran,
                    'nama_unit'    => $santri?->kelas?->unit?->nama_unit
                        ?? $santri?->kelas?->kode_unit
                        ?? strtoupper((string) ($pendaftar?->jenjang ?: $pendaftar?->program_pendaftaran ?: '-')),
                    'kelas_sekarang' => $santri?->kelas?->nama_kelas ?? $pendaftar?->kode_kelas_diterima,
                    'tahun_ajaran'   => $santri?->kelas?->tahun_ajaran,
                    'status'         => $santri?->status ?? $pendaftar?->status_verifikasi,
                    'is_anak_guru'   => (bool) ($santri?->is_anak_guru ?? $pendaftar?->is_anak_guru),
                ],
                'ringkasan' => [
                    'jumlah_invoice' => $allInvoices->count(),
                    'total_tagihan'  => $totalTagihan,
                    'total_dibayar'  => $totalDibayar,
                    'total_tunggakan' => $totalTunggakan,
                ],
                'invoice' => $allInvoices,
                'spp_settings' => $sppSettings,
                'ppdb_selection' => $ppdbSelection,
            ],
        ]);
    }

    /**
     * Normalize ppdb_tagihan row to unified invoice format
     */
    private function normalizePpdbTagihanRow($row): array
    {
        $status = (string) ($row->status_pembayaran ?? 'menunggu_dp');
        $normalizedStatus = $this->normalizeStatusForFrontend($status);
        $isPaid = in_array($normalizedStatus, ['lunas']);
        
        $nominalTagihan = (float) ($row->nominal_tagihan ?? 0);
        $jumlahTerbayar = (float) ($row->jumlah_terbayar ?? 0);
        
        return [
            'id_pembayaran'   => (int) $row->id_tagihan,
            'nomor_invoice'   => "INV-PPDB-{$row->id_tagihan}",
            'periode_tagihan' => $row->periode_tagihan ?? null,
            'rincian_tagihan' => $row->nama_tagihan ?? 'Tagihan Infaq PPDB',
            'jenis_tagihan'   => 'ppdb',
            'jumlah_tagihan'  => $nominalTagihan,
            'jumlah_dibayar'  => $isPaid ? $jumlahTerbayar : 0,
            'jumlah_tunggakan' => $isPaid ? 0 : max($nominalTagihan - $jumlahTerbayar, 0),
            'status'          => $status,
            'status_key'      => $normalizedStatus,
            'status_label'    => $this->buildStatusLabel($status),
            'waktu_invoice'   => $row->created_at ?? null,
            'kwitansi_tersedia' => false, // ppdb_tagihan doesn't have kwitansi integration yet
            'kwitansi_url'    => null,
            'bukti_bayar_path' => $row->bukti_bayar_path ?? null,
            'bukti_bayar_url'  => !empty($row->bukti_bayar_path) ? Storage::url($row->bukti_bayar_path) : null,
            'catatan_bayar'    => $row->catatan_bayar ?? null,
            'jumlah_minimum_dp' => (float) ($row->jumlah_minimum_dp ?? ($nominalTagihan * 0.5)), // Use column or calculate 50% DP
            'bulan' => $row->bulan_tagihan ?? null,
            '_source' => 'ppdb_tagihan',
            '_sort_time' => $row->created_at ?? $row->updated_at ?? null,
        ];
    }


    /**
     * Halaman proses pembayaran: filter master data santri + daftar tagihan/invoice.
     */
    public function proses(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->query('page', 1));

        $statusFilter = $request->filled('status') ? $this->normalizeStatusForFrontend((string) $request->status) : null;

        $santriQuery = DataSantri::query()
            ->with(['kelas.unit'])
            ->when($request->filled('kode_kelas'), fn ($q) => $q->where('kode_kelas', (string) $request->kode_kelas))
            ->when($request->filled('kode_unit'), function ($q) use ($request) {
                $kodeUnit = (string) $request->kode_unit;
                $q->whereHas('kelas', fn ($kelasQuery) => $kelasQuery->where('kode_unit', $kodeUnit));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->q);
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nama_lengkap_santri');

        // Fetch all matching santri to determine their invoice-based status
        $allSantri = $santriQuery->get();
        $idSantriList = $allSantri->pluck('id_santri')->filter()->values();

        // Get SPP invoices for these santri
        $invoiceBySantri = PembayaranSpp::query()
            ->with(['setting.kategoriTagihan', 'kwitansi'])
            ->whereIn('id_santri', $idSantriList)
            ->orderByDesc('id_pembayaran')
            ->get()
            ->groupBy('id_santri');

        // Get Administrasi Bebas for these santri
        $bebasBySantri = \App\Models\AdministrasiBebas::query()
            ->with(['pembayaran', 'kwitansi'])
            ->whereIn('id_santri', $idSantriList)
            ->get()
            ->groupBy('id_santri');

        $filteredSantri = collect();
        foreach ($allSantri as $santri) {
            $invoices = $invoiceBySantri->get($santri->id_santri) ?? collect();
            $bebasItems = $bebasBySantri->get($santri->id_santri) ?? collect();

            $totalTagihanBebas = (float) $bebasItems->sum('total_tagihan');
            $totalDibayarBebas = (float) $bebasItems->sum(fn($b) => $b->total_tagihan - $b->sisa);
            $totalTunggakanBebas = (float) $bebasItems->sum('sisa');

            $totalTagihan = (float) $invoices
                ->reject(fn ($item) => $this->isCanceledStatus((string) $item->status))
                ->sum('nominal_bayar') + $totalTagihanBebas;

            $totalDibayar = (float) $invoices
                ->filter(fn ($item) => $this->isPaidStatus((string) $item->status))
                ->sum('nominal_bayar') + $totalDibayarBebas;

            $totalTunggakan = max($totalTagihan - $totalDibayar, 0);

            $paymentStatus = 'menunggu_pembayaran';
            if ($totalTagihan > 0) {
                if ($totalTunggakan <= 0) {
                    $paymentStatus = 'lunas';
                } elseif ($invoices->contains(fn($i) => $this->normalizeStatusForFrontend($i->status) === 'menunggu_konfirmasi')) {
                    $paymentStatus = 'menunggu_konfirmasi';
                }
            }

            if ($statusFilter !== null && $paymentStatus !== $statusFilter) {
                continue;
            }

            $mappedInvoices = $invoices->map(function (PembayaranSpp $row) {
                return [
                    'id_pembayaran' => $row->id_pembayaran,
                    'nomor_invoice' => $this->buildNomorInvoice($row->id_pembayaran),
                    'periode_tagihan' => $row->setting?->periode,
                    'rincian_tagihan' => $row->bulan 
                        ? (($row->setting?->kategoriTagihan?->nama_tagihan ?: $row->setting?->keterangan ?: 'SPP') . ' - ' . $row->bulan) 
                        : ($row->setting?->kategoriTagihan?->nama_tagihan ?: $row->setting?->keterangan ?: 'Tagihan SPP'),
                    'jumlah_tagihan' => (float) ($row->nominal_bayar ?? 0),
                    'jumlah_dibayar' => $this->isPaidStatus((string) $row->status) ? (float) ($row->nominal_bayar ?? 0) : 0,
                    'jumlah_tunggakan' => $this->isPaidStatus((string) $row->status) ? 0 : (float) ($row->nominal_bayar ?? 0),
                    'status' => $row->status,
                    'status_key' => $this->normalizeStatusForFrontend((string) $row->status),
                    'status_label' => $this->buildStatusLabel((string) $row->status),
                    'waktu_invoice' => optional($row->tanggal_bayar)->format('Y-m-d H:i:s'),
                    'kwitansi_tersedia' => (bool) $row->kwitansi,
                    'kwitansi_url' => $row->kwitansi?->file_path_pdf ? Storage::url($row->kwitansi->file_path_pdf) : null,
                ];
            });

            $mappedBebasInvoices = $bebasItems->map(function (\App\Models\AdministrasiBebas $row) {
                return [
                    'id_pembayaran' => $row->id_admin_bebas,
                    'nomor_invoice' => "INV-BEBAS-" . str_pad((string) $row->id_admin_bebas, 5, '0', STR_PAD_LEFT),
                    'periode_tagihan' => $row->tahun_ajaran ?? null,
                    'rincian_tagihan' => $row->deskripsi ?: 'Administrasi Bebas',
                    'jumlah_tagihan' => (float) ($row->total_tagihan ?? 0),
                    'jumlah_dibayar' => (float) (($row->total_tagihan ?? 0) - ($row->sisa ?? 0)),
                    'jumlah_tunggakan' => (float) ($row->sisa ?? 0),
                    'status' => strtolower($row->status),
                    'status_key' => strtolower($row->status) === 'lunas' ? 'lunas' : 'belum_lunas',
                    'status_label' => strtolower($row->status) === 'lunas' ? 'Lunas' : 'Belum Lunas',
                    'waktu_invoice' => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : null,
                    'kwitansi_tersedia' => (bool) $row->kwitansi->isNotEmpty(),
                    'kwitansi_url' => $row->kwitansi->first()?->file_path_pdf ? Storage::url($row->kwitansi->first()->file_path_pdf) : null,
                ];
            });

            $allSantriInvoices = $mappedInvoices->merge($mappedBebasInvoices)->values();

            $filteredSantri->push([
                'id_santri' => $santri->id_santri,
                'nama_lengkap' => $santri->nama_lengkap_santri,
                'jenis_kelamin' => $santri->jenis_kelamin,
                'nomor_induk' => $santri->nomor_induk,
                'kode_kelas' => $santri->kode_kelas,
                'kode_unit' => $santri->kelas?->kode_unit,
                'unit_sekarang' => $santri->kelas?->unit?->nama_unit ?? $santri->kelas?->kode_unit,
                'kelas_sekarang' => $santri->kelas?->nama_kelas,
                'status' => $paymentStatus,
                'invoice' => $allSantriInvoices,
                'is_anak_guru' => (bool) $santri->is_anak_guru,
            ]);
        }

        $total = $filteredSantri->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $filteredSantri->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(max($total, 1) / $perPage),
            ]
        ]);
    }

    /**
     * Halaman verifikasi pembayaran untuk admin.
     */
    public function verifikasi(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $statusFilter = $request->filled('status')
            ? $this->normalizeStatusForStorage((string) $request->status)
            : null;

        $query = PembayaranSpp::query()
            ->with(['santri.kelas.unit', 'pendaftarPpdb', 'setting.kategoriTagihan'])
            ->when($statusFilter !== null, fn ($q) => $q->where('status', $statusFilter))
            ->when($request->filled('jenis_transaksi'), function ($q) use ($request) {
                $jenis = mb_strtolower(trim((string) $request->jenis_transaksi));
                if (in_array($jenis, ['ppdb', 'administrasi'], true)) {
                    $q->whereNotNull('id_pendaftaran');
                } elseif (in_array($jenis, ['spp', 'tagihan'], true)) {
                    $q->whereNull('id_pendaftaran');
                }
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->q);
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('id_pembayaran', 'like', "%{$keyword}%")
                        ->orWhereHas('santri', function ($santriQuery) use ($keyword) {
                            $santriQuery->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                                ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('pendaftarPpdb', function ($ppdbQuery) use ($keyword) {
                            $ppdbQuery->where('nama_calon', 'like', "%{$keyword}%")
                                ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                                ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('id_pembayaran')
            ->paginate($perPage);

        $query->getCollection()->transform(function (PembayaranSpp $row) {
            $isPpdb = !empty($row->id_pendaftaran);
            $namaUnit = $row->santri?->kelas?->unit?->nama_unit
                ?? $row->santri?->kelas?->kode_unit
                ?? strtoupper((string) ($row->pendaftarPpdb?->jenjang ?: $row->pendaftarPpdb?->program_pendaftaran ?: '-'));

            $nomorInduk = $row->santri?->nomor_induk
                ?? $row->pendaftarPpdb?->nomor_induk_generated
                ?? $row->pendaftarPpdb?->no_pendaftaran_final
                ?? $row->pendaftarPpdb?->no_pendaftaran
                ?? '-';

            return [
                'id_pembayaran' => $row->id_pembayaran,
                'nama_unit' => $namaUnit,
                'nomor_induk' => $nomorInduk,
                'nama_lengkap' => $row->santri?->nama_lengkap_santri ?? $row->pendaftarPpdb?->nama_calon ?? '-',
                'nomor_invoice' => $this->buildNomorInvoice($row->id_pembayaran),
                'total_pembayaran' => (float) ($row->nominal_bayar ?? 0),
                'jenis_transaksi' => $isPpdb
                    ? 'Administrasi PPDB'
                    : ($row->bulan ? (($row->setting?->kategoriTagihan?->nama_tagihan ?: 'SPP') . ' - ' . $row->bulan) : ($row->setting?->kategoriTagihan?->nama_tagihan ?: 'Tagihan')),
                'status_pembayaran' => $this->normalizeStatusForFrontend((string) $row->status),
                'status_label' => $this->buildStatusLabel((string) $row->status),
                'waktu_invoice' => optional($row->tanggal_bayar)->format('Y-m-d H:i:s'),
                'bukti_bayar_path' => $row->bukti_bayar_path,
                'catatan_bayar' => $row->catatan_bayar,
                'aksi' => ['detail', 'status', 'hapus'],
                'is_anak_guru' => (bool) ($row->santri?->is_anak_guru ?? $row->pendaftarPpdb?->is_anak_guru),
            ];
        });

        return response()->json($query);
    }

    /**
     * Detail invoice untuk halaman khusus check detail pembayaran.
     */
    public function detail(int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::query()
            ->with(['santri.kelas.unit', 'pendaftarPpdb', 'setting.kategoriTagihan', 'rekening', 'kwitansi'])
            ->findOrFail($id);

        $riwayat = PembayaranSpp::query()
            ->with(['setting.kategoriTagihan'])
            ->when($pembayaran->id_santri, fn ($q) => $q->where('id_santri', $pembayaran->id_santri))
            ->when(!$pembayaran->id_santri && $pembayaran->id_pendaftaran, fn ($q) => $q->where('id_pendaftaran', $pembayaran->id_pendaftaran))
            ->orderByDesc('id_pembayaran')
            ->limit(20)
            ->get()
            ->map(fn (PembayaranSpp $row) => [
                'id_pembayaran' => $row->id_pembayaran,
                'nomor_invoice' => $this->buildNomorInvoice($row->id_pembayaran),
                'nominal_bayar' => (float) ($row->nominal_bayar ?? 0),
                'status' => $this->normalizeStatusForFrontend((string) $row->status),
                'status_label' => $this->buildStatusLabel((string) $row->status),
                'tanggal_bayar' => optional($row->tanggal_bayar)->format('Y-m-d H:i:s'),
                'jenis_tagihan' => $row->bulan ? (($row->setting?->kategoriTagihan?->nama_tagihan ?: 'SPP') . ' - ' . $row->bulan) : $row->setting?->kategoriTagihan?->nama_tagihan,
            ])
            ->values();

        $tagihanKustom = collect();
        if ($pembayaran->id_santri) {
            $tagihanKustom = SppSetting::query()
                ->where('id_santri', $pembayaran->id_santri)
                ->orderByDesc('id_setting')
                ->get()
                ->map(fn (SppSetting $setting) => [
                    'id_setting' => $setting->id_setting,
                    'kategori' => $setting->kategoriTagihan?->nama_tagihan,
                    'jumlah' => (float) ($setting->jumlah ?? 0),
                    'periode' => $setting->periode,
                    'keterangan' => $setting->keterangan,
                ])
                ->values();
        }

        return response()->json([
            'data' => [
                'invoice' => [
                    'id_pembayaran' => $pembayaran->id_pembayaran,
                    'nomor_invoice' => $this->buildNomorInvoice($pembayaran->id_pembayaran),
                    'status' => $this->normalizeStatusForFrontend((string) $pembayaran->status),
                    'status_label' => $this->buildStatusLabel((string) $pembayaran->status),
                    'nominal_bayar' => (float) ($pembayaran->nominal_bayar ?? 0),
                    'tanggal_bayar' => optional($pembayaran->tanggal_bayar)->format('Y-m-d H:i:s'),
                    'tanggal_verifikasi' => optional($pembayaran->tanggal_verifikasi)->format('Y-m-d H:i:s'),
                    'tanggal_konfirmasi' => optional($pembayaran->tanggal_konfirmasi)->format('Y-m-d H:i:s'),
                    'metode_bayar' => $pembayaran->metode_bayar,
                    'bukti_bayar_path' => $pembayaran->bukti_bayar_path,
                    'bukti_bayar_url' => $pembayaran->bukti_bayar_path
                        ? Storage::url($pembayaran->bukti_bayar_path)
                        : null,
                    'catatan_bayar' => $pembayaran->catatan_bayar,
                ],
                'santri' => $pembayaran->santri ? [
                    'id_santri' => $pembayaran->santri->id_santri,
                    'nomor_induk' => $pembayaran->santri->nomor_induk,
                    'nama_lengkap' => $pembayaran->santri->nama_lengkap_santri,
                    'unit' => $pembayaran->santri->kelas?->unit?->nama_unit ?? $pembayaran->santri->kelas?->kode_unit,
                    'kelas' => $pembayaran->santri->kelas?->nama_kelas,
                ] : null,
                'ppdb' => $pembayaran->pendaftarPpdb ? [
                    'id_pendaftaran' => $pembayaran->pendaftarPpdb->id_pendaftaran,
                    'nama_calon' => $pembayaran->pendaftarPpdb->nama_calon,
                    'nomor_pendaftaran' => $pembayaran->pendaftarPpdb->no_pendaftaran_final ?: $pembayaran->pendaftarPpdb->no_pendaftaran,
                    'status_verifikasi' => $pembayaran->pendaftarPpdb->status_verifikasi,
                ] : null,
                'riwayat_pembayaran' => $riwayat,
                'tagihan_kustom' => $tagihanKustom,
                'kwitansi' => $pembayaran->kwitansi ? [
                    'id_kwitansi' => $pembayaran->kwitansi->id_kwitansi,
                    'file_path_pdf' => $pembayaran->kwitansi->file_path_pdf,
                    'file_url_pdf' => $pembayaran->kwitansi->file_path_pdf ? Storage::url($pembayaran->kwitansi->file_path_pdf) : null,
                    'jumlah' => $pembayaran->kwitansi->jumlah,
                ] : null,
            ],
        ]);
    }

    /**
     * Upload bukti bayar oleh santri/pendaftar.
     */
    public function uploadBuktiBayar(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);

        $request->validate([
            'bukti_bayar' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'catatan_bayar' => ['nullable', 'string', 'max:500'],
            'metode_bayar' => ['nullable', 'string', 'max:100'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
        ]);

        // Jumlah bayar otomatis = jumlah tunggakan yang ada
        // User TIDAK perlu input jumlah, karena sudah pasti dari tagihan
        $jumlahBayar = (float) $pembayaran->nominal_bayar;

        $path = $request->file('bukti_bayar')->store('pembayaran/bukti', 'public');

        $pembayaran->update([
            'bukti_bayar_path' => $path,
            'catatan_bayar' => $request->catatan_bayar,
            'metode_bayar' => $request->metode_bayar ?? $pembayaran->metode_bayar,
            'id_rekening' => $request->id_rekening ?? $pembayaran->id_rekening,
            'status' => 'menunggu_verifikasi',
            'tanggal_bayar' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Bukti bayar berhasil diunggah. Menunggu konfirmasi admin.',
            'data' => [
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'status' => 'menunggu_verifikasi',
                'bukti_bayar_url' => Storage::url($path),
                'jumlah_bayar' => $jumlahBayar,
            ],
        ]);
    }

    /**
     * Verifikasi/konfirmasi pembayaran oleh admin.
     */
    public function konfirmasiVerifikasi(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);

        $request->validate([
            'aksi' => ['required', 'in:terima,tolak'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $statusBaru = $request->aksi === 'terima' ? 'terverifikasi' : 'ditolak';

        $pembayaran->update([
            'status' => $statusBaru,
            'tanggal_verifikasi' => Carbon::now(),
            'tanggal_konfirmasi' => Carbon::now(),
        ]);

        return response()->json([
            'message' => $request->aksi === 'terima'
                ? 'Pembayaran berhasil diverifikasi.'
                : 'Pembayaran ditolak.',
            'data' => [
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'status' => $this->normalizeStatusForFrontend($statusBaru),
                'status_label' => $this->buildStatusLabel($statusBaru),
            ],
        ]);
    }

    /**
     * Ringkasan cepat kartu dashboard pembayaran terpadu.
     */
    public function ringkasan(): JsonResponse
    {
        $base = PembayaranSpp::query();

        $total = (clone $base)->count();
        $ppdb = (clone $base)->whereNotNull('id_pendaftaran')->count();
        $spp = (clone $base)->whereNull('id_pendaftaran')->count();

        $menunggu = (clone $base)->where('status', 'menunggu_verifikasi')->count();
        $terverifikasi = (clone $base)->where('status', 'terverifikasi')->count();
        $ditolak = (clone $base)->where('status', 'ditolak')->count();

        $nominalTotal = (float) ((clone $base)->sum('nominal_bayar') ?? 0);
        $nominalTerverifikasi = (float) ((clone $base)
            ->where('status', 'terverifikasi')
            ->sum('nominal_bayar') ?? 0);

        return response()->json([
            'data' => [
                'total_transaksi' => $total,
                'total_ppdb' => $ppdb,
                'total_spp' => $spp,
                'status_menunggu_verifikasi' => $menunggu,
                'status_terverifikasi' => $terverifikasi,
                'status_ditolak' => $ditolak,
                'nominal_total' => $nominalTotal,
                'nominal_terverifikasi' => $nominalTerverifikasi,
            ],
        ]);
    }


    /**
     * Issue #11: Tagihan milik santri yang sedang login.
     * Mencakup SPP (bulanan + infaq), Tagihan PPDB, dan Administrasi Bebas.
     * Endpoint: GET /api/administrasi/pembayaran/tagihan-saya
     */
    public function tagihanSaya(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve id_santri dari akun santri yang login
        $idSantri = null;
        $nomorInduk = null;

        if ($user instanceof \App\Models\DataAkunSantri) {
            $nomorInduk = $user->nomor_induk;
            $santri = \App\Models\DataSantri::where('nomor_induk', $nomorInduk)->first();
            $idSantri = $santri?->id_santri;
        } elseif ($user instanceof \App\Models\DataSantri) {
            $idSantri = $user->id_santri;
            $nomorInduk = $user->nomor_induk;
        }

        if (!$idSantri) {
            return response()->json([
                'message' => 'Data santri tidak ditemukan untuk akun ini.',
                'data' => [
                    'tagihan' => [],
                    'ringkasan' => ['total_tagihan' => 0, 'total_dibayar' => 0, 'total_tunggakan' => 0],
                ],
            ]);
        }

        $santriData = \App\Models\DataSantri::with(['kelas.unit'])->find($idSantri);

        // 1. Tagihan SPP & Infaq dari pembayaran_spp (SPP + PPDB)
        $sppItems = PembayaranSpp::query()
            ->with(['setting.kategoriTagihan', 'kwitansi'])
            ->where(function ($q) use ($idSantri) {
                $q->where('id_santri', $idSantri);
                // Juga ambil yang terhubung via id_pendaftaran (PPDB yang belum linked)
                $pendaftaranIds = \App\Models\PpdbPendaftar::where('id_santri', $idSantri)
                    ->pluck('id_pendaftaran');
                if ($pendaftaranIds->isNotEmpty()) {
                    $q->orWhereIn('id_pendaftaran', $pendaftaranIds);
                }
            })
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('id_pembayaran')
            ->get();

        $sppTagihan = $sppItems->map(fn (PembayaranSpp $item) => [
            'id'              => $item->id_pembayaran,
            'nomor_invoice'   => $this->buildNomorInvoice($item->id_pembayaran),
            'jenis_tagihan'   => empty($item->id_pendaftaran)
                ? (strtolower((string) ($item->jenis_tagihan ?? 'spp')))
                : 'ppdb',
            'rincian_tagihan' => $item->bulan
                ? (($item->setting?->kategoriTagihan?->nama_tagihan ?: $item->setting?->keterangan ?: (empty($item->id_pendaftaran) ? 'SPP' : 'PPDB')) . ' — ' . $item->bulan)
                : ($item->setting?->kategoriTagihan?->nama_tagihan ?: $item->setting?->keterangan ?: (empty($item->id_pendaftaran) ? 'Tagihan SPP' : 'Tagihan PPDB')),
            'periode_tagihan' => $item->setting?->periode,
            'bulan'           => $item->bulan,
            'jumlah_tagihan'  => (float) ($item->nominal_bayar ?? 0),
            'jumlah_dibayar'  => $this->isPaidStatus((string) $item->status) ? (float) ($item->nominal_bayar ?? 0) : 0,
            'jumlah_tunggakan' => $this->isPaidStatus((string) $item->status) ? 0 : (float) ($item->nominal_bayar ?? 0),
            'status'          => $item->status,
            'status_key'      => $this->normalizeStatusForFrontend((string) $item->status),
            'status_label'    => $this->buildStatusLabel((string) $item->status),
            'waktu_invoice'   => optional($item->tanggal_bayar)->format('Y-m-d H:i:s'),
            'kwitansi_tersedia' => (bool) $item->kwitansi,
            'kwitansi_url'    => $item->kwitansi?->file_path_pdf ? Storage::url($item->kwitansi->file_path_pdf) : null,
            'bukti_bayar_url'  => $item->bukti_bayar_path ? Storage::url($item->bukti_bayar_path) : null,
        ])->values()->all();

        // 2. Administrasi Bebas
        $bebasItems = \App\Models\AdministrasiBebas::with(['kwitansi'])
            ->where('id_santri', $idSantri)
            ->orderByDesc('id_admin_bebas')
            ->get();

        $bebasTagihan = $bebasItems->map(fn (\App\Models\AdministrasiBebas $item) => [
            'id'              => $item->id_admin_bebas,
            'nomor_invoice'   => 'INV-BEBAS-' . str_pad((string) $item->id_admin_bebas, 5, '0', STR_PAD_LEFT),
            'jenis_tagihan'   => 'bebas',
            'rincian_tagihan' => $item->deskripsi ?: 'Administrasi Bebas',
            'periode_tagihan' => $item->tahun_ajaran,
            'bulan'           => null,
            'jumlah_tagihan'  => (float) ($item->total_tagihan ?? 0),
            'jumlah_dibayar'  => (float) (($item->total_tagihan ?? 0) - ($item->sisa ?? 0)),
            'jumlah_tunggakan' => (float) ($item->sisa ?? 0),
            'status'          => strtolower($item->status),
            'status_key'      => strtolower($item->status) === 'lunas' ? 'lunas' : 'belum_lunas',
            'status_label'    => strtolower($item->status) === 'lunas' ? 'Lunas' : 'Belum Lunas',
            'waktu_invoice'   => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
            'kwitansi_tersedia' => $item->kwitansi->isNotEmpty(),
            'kwitansi_url'    => $item->kwitansi->first()?->file_path_pdf ? Storage::url($item->kwitansi->first()->file_path_pdf) : null,
            'bukti_bayar_url'  => null,
        ])->values()->all();

        // Gabung semua
        $semuaTagihan = array_merge($sppTagihan, $bebasTagihan);
        usort($semuaTagihan, fn ($a, $b) => strcmp((string)($b['waktu_invoice'] ?? ''), (string)($a['waktu_invoice'] ?? '')));

        // Ringkasan
        $totalTagihan  = array_sum(array_column($semuaTagihan, 'jumlah_tagihan'));
        $totalDibayar  = array_sum(array_column($semuaTagihan, 'jumlah_dibayar'));
        $totalTunggakan = max($totalTagihan - $totalDibayar, 0);

        return response()->json([
            'data' => [
                'santri' => [
                    'id_santri'    => $santriData?->id_santri,
                    'nomor_induk'  => $santriData?->nomor_induk,
                    'nama_lengkap' => $santriData?->nama_lengkap_santri,
                    'kelas'        => $santriData?->kelas?->nama_kelas,
                    'unit'         => $santriData?->kelas?->unit?->nama_unit,
                    'is_anak_guru' => (bool) $santriData?->is_anak_guru,
                ],
                'ringkasan' => [
                    'total_tagihan'   => $totalTagihan,
                    'total_dibayar'   => $totalDibayar,
                    'total_tunggakan' => $totalTunggakan,
                    'jumlah_invoice'  => count($semuaTagihan),
                ],
                'tagihan' => $semuaTagihan,
            ],
        ]);
    }

    /**
     * Opsi filter untuk UX frontend agar tidak membingungkan.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'jenis_pembayaran' => [
                    ['value' => 'ppdb', 'label' => 'PPDB'],
                    ['value' => 'spp', 'label' => 'SPP'],
                ],
                'status' => [
                    ['value' => 'menunggu_pembayaran', 'label' => 'Menunggu Pembayaran'],
                    ['value' => 'menunggu_konfirmasi', 'label' => 'Menunggu Konfirmasi'],
                    ['value' => 'dibatalkan', 'label' => 'Dibatalkan'],
                ],
                'aksi' => ['detail', 'status', 'hapus'],
            ],
        ]);
    }

    private function normalizeStatusForStorage(string $status): string
    {
        $normalized = mb_strtolower(trim($status));

        return match ($normalized) {
            'menunggu_pembayaran', 'pending' => 'menunggu_pembayaran',
            'menunggu_konfirmasi', 'menunggu_verifikasi' => 'menunggu_verifikasi',
            'lunas', 'terverifikasi' => 'terverifikasi',
            'dibatalkan', 'ditolak', 'batal' => 'ditolak',
            default => $normalized,
        };
    }

    private function normalizeStatusForFrontend(string $status): string
    {
        $normalized = mb_strtolower(trim($status));

        return match ($normalized) {
            // SPP status mappings (existing)
            'menunggu_verifikasi' => 'menunggu_konfirmasi',
            'terverifikasi' => 'lunas',
            'ditolak' => 'dibatalkan',
            
            // PPDB Infaq status mappings (new - based on ppdb_tagihan enum)
            'menunggu_dp' => 'menunggu_pembayaran',
            'dp_terbayar' => 'menunggu_konfirmasi',
            'terlambat' => 'menunggu_pembayaran',
            
            // Default: return as-is
            default => $normalized,
        };
    }

    private function buildStatusLabel(string $status): string
    {
        return match ($this->normalizeStatusForFrontend($status)) {
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'dibatalkan' => 'Dibatalkan',
            'lunas' => 'Lunas',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function isPaidStatus(string $status): bool
    {
        return in_array($this->normalizeStatusForStorage($status), ['terverifikasi'], true);
    }

    private function isCanceledStatus(string $status): bool
    {
        return in_array($this->normalizeStatusForStorage($status), ['ditolak'], true);
    }

    private function buildNomorInvoice(int $idPembayaran): string
    {
        return '#' . str_pad((string) $idPembayaran, 8, '0', STR_PAD_LEFT);
    }
}
