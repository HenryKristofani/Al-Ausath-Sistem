<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataSantri;
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
                'rekening' => $row->rekening ? [
                    'id_rekening' => $row->rekening->id_rekening,
                    'nama_bank' => $row->rekening->nama_bank ?? null,
                    'nomor_rekening' => $row->rekening->nomor_rekening ?? null,
                ] : null,
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
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->query('page', 1));

        $rows = PembayaranSpp::query()
            ->with(['santri.kelas.unit', 'pendaftarPpdb', 'setting'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->q);
                $q->where(function ($sub) use ($keyword) {
                    $sub->whereHas('santri', function ($santriQuery) use ($keyword) {
                        $santriQuery->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                            ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                    })->orWhereHas('pendaftarPpdb', function ($ppdbQuery) use ($keyword) {
                        $ppdbQuery->where('nama_calon', 'like', "%{$keyword}%")
                            ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                            ->orWhere('no_pendaftaran_final', 'like', "%{$keyword}%");
                    });
                });
            })
            ->orderByDesc('id_pembayaran')
            ->get();

        $grouped = $rows->groupBy(function (PembayaranSpp $row) {
            if (!empty($row->id_santri)) {
                return 'santri:' . $row->id_santri;
            }

            return 'ppdb:' . (int) $row->id_pendaftaran;
        });

        $data = $grouped->map(function (Collection $items) {
            /** @var PembayaranSpp $first */
            $first = $items->first();
            $santri = $first?->santri;
            $pendaftar = $first?->pendaftarPpdb;

            $namaUnit = $santri?->kelas?->unit?->nama_unit
                ?? $santri?->kelas?->kode_unit
                ?? strtoupper((string) ($pendaftar?->jenjang ?: $pendaftar?->program_pendaftaran ?: '-'));

            $nomorInduk = $santri?->nomor_induk
                ?? $pendaftar?->nomor_induk_generated
                ?? $pendaftar?->no_pendaftaran_final
                ?? $pendaftar?->no_pendaftaran
                ?? '-';

            $totalTagihan = (float) $items
                ->reject(fn (PembayaranSpp $item) => $this->isCanceledStatus((string) $item->status))
                ->sum('nominal_bayar');

            $totalDibayar = (float) $items
                ->filter(fn (PembayaranSpp $item) => $this->isPaidStatus((string) $item->status))
                ->sum('nominal_bayar');

            $totalTunggakan = max($totalTagihan - $totalDibayar, 0);

            $statusSantri = $santri?->status;
            if (!$statusSantri && $pendaftar) {
                $statusSantri = (string) ($pendaftar->status_verifikasi ?: 'calon_santri');
            }

            return [
                'nama_unit' => $namaUnit,
                'nomor_induk' => $nomorInduk,
                'nama_lengkap' => $santri?->nama_lengkap_santri ?? $pendaftar?->nama_calon ?? '-',
                'kelas_sekarang' => $santri?->kelas?->nama_kelas ?? $pendaftar?->kode_kelas_diterima ?? '-',
                'tahun_ajaran' => $santri?->kelas?->tahun_ajaran ?? '-',
                'status' => $statusSantri,
                'total_tagihan' => $totalTagihan,
                'total_dibayar' => $totalDibayar,
                'total_tunggakan' => $totalTunggakan,
                'jumlah_invoice' => $items->count(),
                'sumber_data' => $santri ? 'master_data_santri' : 'ppdb',
                'id_santri' => $santri?->id_santri,
                'id_pendaftaran' => $pendaftar?->id_pendaftaran,
            ];
        })->values();

        $paged = $data->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $paged,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $data->count(),
                'last_page' => (int) ceil(max($data->count(), 1) / $perPage),
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

        $santriPage = DataSantri::query()
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
            ->orderBy('nama_lengkap_santri')
            ->paginate($perPage);

        $idSantri = $santriPage->getCollection()->pluck('id_santri')->filter()->values();

        $invoiceBySantri = PembayaranSpp::query()
            ->with(['setting.kategoriTagihan', 'kwitansi'])
            ->whereIn('id_santri', $idSantri)
            ->orderByDesc('id_pembayaran')
            ->get()
            ->groupBy('id_santri');

        $santriPage->getCollection()->transform(function (DataSantri $santri) use ($invoiceBySantri) {
            $invoices = ($invoiceBySantri->get($santri->id_santri) ?? collect())->map(function (PembayaranSpp $row) {
                return [
                    'id_pembayaran' => $row->id_pembayaran,
                    'nomor_invoice' => $this->buildNomorInvoice($row->id_pembayaran),
                    'periode_tagihan' => $row->setting?->periode,
                    'rincian_tagihan' => $row->setting?->kategoriTagihan?->nama_tagihan,
                    'jumlah_tagihan' => (float) ($row->nominal_bayar ?? 0),
                    'jumlah_dibayar' => $this->isPaidStatus((string) $row->status) ? (float) ($row->nominal_bayar ?? 0) : 0,
                    'jumlah_tunggakan' => $this->isPaidStatus((string) $row->status) ? 0 : (float) ($row->nominal_bayar ?? 0),
                    'status' => $row->status,
                    'status_key' => $this->normalizeStatusForFrontend((string) $row->status),
                    'status_label' => $this->buildStatusLabel((string) $row->status),
                    'waktu_invoice' => optional($row->tanggal_bayar)->format('Y-m-d H:i:s'),
                    'kwitansi_tersedia' => (bool) $row->kwitansi,
                ];
            })->values();

            return [
                'id_santri' => $santri->id_santri,
                'nama_lengkap' => $santri->nama_lengkap_santri,
                'jenis_kelamin' => $santri->jenis_kelamin,
                'nomor_induk' => $santri->nomor_induk,
                'unit_sekarang' => $santri->kelas?->unit?->nama_unit ?? $santri->kelas?->kode_unit,
                'kelas_sekarang' => $santri->kelas?->nama_kelas,
                'status' => $santri->status,
                'invoice' => $invoices,
            ];
        });

        return response()->json($santriPage);
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
                    : ($row->setting?->kategoriTagihan?->nama_tagihan ?: 'Tagihan'),
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
                'jenis_tagihan' => $row->setting?->kategoriTagihan?->nama_tagihan,
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
                'bukti_bayar_url' => Storage::disk('public')->url($path),
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
