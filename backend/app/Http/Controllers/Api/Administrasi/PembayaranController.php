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

        // 2. Fetch base IDs from entities to support search filters
        $santriQuery = DataSantri::query()
            ->select('id_santri', 'nomor_induk', 'nama_lengkap_santri')
            ->when($nomorInduk, fn ($q) => $q->where('nomor_induk', $nomorInduk))
            ->when($keyword, function ($q) use ($keyword) {
                $q->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                  ->orWhere('nomor_induk', 'like', "%{$keyword}%");
            });

        $ppdbQuery = PpdbPendaftar::query()
            ->select('id_pendaftaran', 'id_santri', 'no_pendaftaran', 'no_pendaftaran_final', 'nomor_induk_generated', 'nama_calon')
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
        // Find the first payment row matching by id_santri OR id_pendaftaran
        $row = PembayaranSpp::query()
            ->with(['santri.kelas.unit', 'pendaftarPpdb', 'setting.kategoriTagihan', 'kwitansi'])
            ->where(function ($q) use ($id) {
                $q->where('id_santri', $id)
                  ->orWhere('id_pendaftaran', $id);
            })
            ->orderByDesc('id_pembayaran')
            ->firstOrFail();

        // Resolve: if this payment has id_santri (SPP bill, or PPDB bill after integration fix),
        // treat the whole detail as a santri-view and fetch ALL bills by id_santri.
        $linkedSantriId = $row->id_santri;
        $isSantri       = !empty($linkedSantriId);

        // Resolve the actual entity objects for profil
        $santri    = $isSantri ? $row->santri : null;
        $pendaftar = $row->pendaftarPpdb;

        // Fetch ALL bills for this entity
        $items = PembayaranSpp::query()
            ->with(['setting.kategoriTagihan', 'kwitansi'])
            ->where(function ($q) use ($isSantri, $linkedSantriId, $row) {
                if ($isSantri) {
                    // Fetch both SPP bills (by id_santri) and PPDB bills (by id_pendaftaran linked to same santri)
                    $q->where('id_santri', $linkedSantriId);
                    if (!empty($row->id_pendaftaran)) {
                        $q->orWhere('id_pendaftaran', $row->id_pendaftaran);
                    }
                } else {
                    $q->where('id_pendaftaran', $row->id_pendaftaran);
                }
            })
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('id_pembayaran')
            ->get();

        $totalTagihan = (float) $items
            ->reject(fn (PembayaranSpp $item) => $this->isCanceledStatus((string) $item->status))
            ->sum('nominal_bayar');

        $totalDibayar = (float) $items
            ->filter(fn (PembayaranSpp $item) => $this->isPaidStatus((string) $item->status))
            ->sum('nominal_bayar');

        $totalTunggakan = max($totalTagihan - $totalDibayar, 0);

        return response()->json([
            'data' => [
                'profil' => [
                    'id'           => $isSantri ? $linkedSantriId : $row->id_pendaftaran,
                    'sumber'       => $isSantri ? 'santri' : 'ppdb',
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
                ],
                'ringkasan' => [
                    'jumlah_invoice' => $items->count(),
                    'total_tagihan'  => $totalTagihan,
                    'total_dibayar'  => $totalDibayar,
                    'total_tunggakan' => $totalTunggakan,
                ],
                'invoice' => $items->map(fn (PembayaranSpp $item) => [
                    'id_pembayaran'   => $item->id_pembayaran,
                    'nomor_invoice'   => $this->buildNomorInvoice($item->id_pembayaran),
                    'periode_tagihan' => $item->setting?->periode,
                    'rincian_tagihan' => $item->bulan 
                        ? (($item->setting?->kategoriTagihan?->nama_tagihan ?: $item->setting?->keterangan ?: 'SPP') . ' - ' . $item->bulan) 
                        : ($item->setting?->kategoriTagihan?->nama_tagihan ?: $item->setting?->keterangan ?: (empty($item->id_pendaftaran) ? 'Tagihan SPP' : 'Tagihan PPDB')),
                    'jenis_tagihan'   => empty($item->id_pendaftaran) ? 'SPP' : 'PPDB',
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
                    'bukti_bayar_url'  => $item->bukti_bayar_path ? Storage::disk('public')->url($item->bukti_bayar_path) : null,
                    'catatan_bayar'    => $item->catatan_bayar,
                ])->values(),
            ],
        ]);
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

        $filteredSantri = collect();
        foreach ($allSantri as $santri) {
            $invoices = $invoiceBySantri->get($santri->id_santri) ?? collect();

            $totalTagihan = (float) $invoices
                ->reject(fn ($item) => $this->isCanceledStatus((string) $item->status))
                ->sum('nominal_bayar');

            $totalDibayar = (float) $invoices
                ->filter(fn ($item) => $this->isPaidStatus((string) $item->status))
                ->sum('nominal_bayar');

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
            })->values();

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
                'invoice' => $mappedInvoices,
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
                        ? Storage::disk('public')->url($pembayaran->bukti_bayar_path)
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
        ]);

        $path = $request->file('bukti_bayar')->store('pembayaran/bukti', 'public');

        $pembayaran->update([
            'bukti_bayar_path' => $path,
            'catatan_bayar' => $request->catatan_bayar,
            'metode_bayar' => $request->metode_bayar ?? $pembayaran->metode_bayar,
            'status' => 'menunggu_verifikasi',
            'tanggal_bayar' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Bukti bayar berhasil diunggah. Menunggu konfirmasi admin.',
            'data' => [
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'status' => 'menunggu_verifikasi',
                'bukti_bayar_url' => Storage::url($path),
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
            'menunggu_verifikasi' => 'menunggu_konfirmasi',
            'terverifikasi' => 'lunas',
            'ditolak' => 'dibatalkan',
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
