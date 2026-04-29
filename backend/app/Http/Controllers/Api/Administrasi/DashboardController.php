<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataSantri;
use App\Models\Pengumuman;
use App\Models\PembayaranSpp;
use App\Models\PpdbPendaftar;
use App\Models\PpdbTesKonfigurasi;
use App\Models\SppGolongan;
use App\Models\SppSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $nextWeek = (clone $today)->addDays(7);

        $ppdbBase = PpdbPendaftar::query();
        $paymentBase = PembayaranSpp::query();
        $announcementBase = Pengumuman::query();
        $sppSettingBase = SppSetting::query();
        $sppGolonganBase = SppGolongan::query();
        $tesConfigBase = PpdbTesKonfigurasi::query();
        $santriBase = DataSantri::query();

        $ppdbTotal = (clone $ppdbBase)->count();
        $ppdbPending = $this->countStatuses($ppdbBase, 'status_verifikasi', ['pending', 'menunggu', 'baru']);
        $ppdbAccepted = $this->countStatuses($ppdbBase, 'status_verifikasi', ['diterima', 'lulus', 'accepted']);
        $ppdbRejected = $this->countStatuses($ppdbBase, 'status_verifikasi', ['ditolak', 'rejected']);
        $ppdbIntegrated = (clone $ppdbBase)
            ->whereRaw("LOWER(COALESCE(status_verifikasi, '')) IN (?, ?, ?)", ['diterima', 'lulus', 'accepted'])
            ->whereNotNull('id_santri')
            ->count();
        $ppdbNeedIntegration = max($ppdbAccepted - $ppdbIntegrated, 0);
        $ppdbWithPayment = (clone $paymentBase)->whereNotNull('id_pendaftaran')->count();
        $ppdbTestActive = (clone $tesConfigBase)->where('fitur_soal_aktif', true)->count();

        $paymentTotal = (clone $paymentBase)->count();
        $paymentPpdb = (clone $paymentBase)->whereNotNull('id_pendaftaran')->count();
        $paymentSpp = (clone $paymentBase)->whereNull('id_pendaftaran')->count();
        $paymentPending = $this->countStatuses($paymentBase, 'status', ['menunggu_verifikasi', 'pending', 'tagihan_dibuat']);
        $paymentVerified = $this->countStatuses($paymentBase, 'status', ['terverifikasi', 'verified', 'lunas', 'paid']);
        $paymentRejected = $this->countStatuses($paymentBase, 'status', ['ditolak', 'rejected']);
        $paymentNominalTotal = (float) ((clone $paymentBase)->sum('nominal_bayar') ?? 0);
        $paymentNominalVerified = (float) ((clone $paymentBase)
            ->whereRaw("LOWER(COALESCE(status, '')) IN (?, ?, ?, ?)", ['terverifikasi', 'verified', 'lunas', 'paid'])
            ->sum('nominal_bayar') ?? 0);

        $announcementTotal = (clone $announcementBase)->count();
        $announcementActive = (clone $announcementBase)->where('is_aktif', true)->count();
        $announcementPinned = (clone $announcementBase)->where('is_pinned', true)->count();
        $announcementEndingSoon = (clone $announcementBase)
            ->whereNotNull('tanggal_selesai')
            ->whereDate('tanggal_selesai', '>=', $today)
            ->whereDate('tanggal_selesai', '<=', $nextWeek)
            ->count();

        $sppSettingTotal = (clone $sppSettingBase)->count();
        $sppGolonganTotal = (clone $sppGolonganBase)->count();
        $santriTotal = (clone $santriBase)
            ->when(Schema::hasColumn('data_santri', 'is_deleted'), function (Builder $query) {
                $query->where(function (Builder $subQuery) {
                    $subQuery->whereNull('is_deleted')->orWhere('is_deleted', false);
                });
            })
            ->count();

        $flow = [
            [
                'key' => 'registrasi',
                'label' => 'Registrasi akun',
                'description' => 'Calon pendaftar membuat akun dan nomor pendaftaran otomatis dibuat.',
                'status' => $ppdbTotal > 0 ? 'done' : 'waiting',
                'count' => $ppdbTotal,
            ],
            [
                'key' => 'biodata',
                'label' => 'Lengkapi biodata',
                'description' => 'Pendaftar melengkapi form dan berkas agar status bisa divalidasi.',
                'status' => $ppdbPending > 0 ? 'active' : 'waiting',
                'count' => $ppdbPending,
            ],
            [
                'key' => 'tes',
                'label' => 'Tes PPDB',
                'description' => 'Jika konfigurasi tes aktif, pendaftar wajib mengerjakan soal.',
                'status' => $ppdbTestActive > 0 ? 'active' : 'waiting',
                'count' => $ppdbTestActive,
            ],
            [
                'key' => 'verifikasi',
                'label' => 'Verifikasi admin',
                'description' => 'Petugas memeriksa data, tes, dan menentukan diterima atau ditolak.',
                'status' => $ppdbAccepted + $ppdbRejected > 0 ? 'done' : 'waiting',
                'count' => $ppdbAccepted + $ppdbRejected,
            ],
            [
                'key' => 'tagihan-ppdb',
                'label' => 'Tagihan PPDB',
                'description' => 'Setelah diterima, sistem atau admin membuat tagihan PPDB.',
                'status' => $ppdbNeedIntegration > 0 || $paymentPpdb > 0 ? 'active' : 'waiting',
                'count' => $paymentPpdb,
            ],
            [
                'key' => 'santri-aktif',
                'label' => 'Santri aktif & SPP',
                'description' => 'Pendaftar yang terintegrasi menjadi santri dan masuk ke administrasi SPP.',
                'status' => $ppdbIntegrated > 0 ? 'done' : 'waiting',
                'count' => $ppdbIntegrated,
            ],
        ];

        return response()->json([
            'message' => 'Ringkasan administrasi berhasil dimuat.',
            'data' => [
                'ppdb' => [
                    'total_pendaftar' => $ppdbTotal,
                    'menunggu_verifikasi' => $ppdbPending,
                    'diterima' => $ppdbAccepted,
                    'ditolak' => $ppdbRejected,
                    'terintegrasi_santri' => $ppdbIntegrated,
                    'perlu_integrasi_santri' => $ppdbNeedIntegration,
                    'tagihan_ppdb_terbuat' => $ppdbWithPayment,
                    'fitur_tes_aktif' => $ppdbTestActive,
                ],
                'spp' => [
                    'total_setting' => $sppSettingTotal,
                    'total_golongan' => $sppGolonganTotal,
                    'total_santri' => $santriTotal,
                    'total_tagihan' => $paymentTotal,
                    'tagihan_ppdb' => $paymentPpdb,
                    'tagihan_spp' => $paymentSpp,
                    'menunggu_verifikasi' => $paymentPending,
                    'terverifikasi' => $paymentVerified,
                    'ditolak' => $paymentRejected,
                ],
                'pengumuman' => [
                    'total' => $announcementTotal,
                    'aktif' => $announcementActive,
                    'pinned' => $announcementPinned,
                    'akan_berakhir' => $announcementEndingSoon,
                ],
                'pembayaran' => [
                    'total' => $paymentTotal,
                    'ppdb' => $paymentPpdb,
                    'spp' => $paymentSpp,
                    'menunggu_verifikasi' => $paymentPending,
                    'terverifikasi' => $paymentVerified,
                    'ditolak' => $paymentRejected,
                    'nominal_total' => $paymentNominalTotal,
                    'nominal_terverifikasi' => $paymentNominalVerified,
                ],
                'flow' => $flow,
                'quick_actions' => [
                    [
                        'label' => 'Kelola PPDB',
                        'href' => '/dashboard/ppdb',
                        'description' => 'Periksa biodata, berkas, tes, dan verifikasi pendaftar.',
                    ],
                    [
                        'label' => 'Kelola SPP',
                        'href' => '/dashboard/spp',
                        'description' => 'Atur golongan, setting tagihan, dan verifikasi pembayaran.',
                    ],
                    [
                        'label' => 'Pengumuman',
                        'href' => '/dashboard/pengumuman',
                        'description' => 'Atur pengumuman untuk PPDB, akademik, dan informasi umum.',
                    ],
                    [
                        'label' => 'Rekap Pembayaran',
                        'href' => '/dashboard/pembayaran',
                        'description' => 'Lihat gabungan pembayaran PPDB dan SPP dalam satu daftar.',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function countStatuses(Builder $query, string $column, array $statuses): int
    {
        $normalizedStatuses = array_values(array_filter(array_map(
            static fn (string $status): string => trim(mb_strtolower($status)),
            $statuses,
        ), static fn (string $status): bool => $status !== ''));

        if ($normalizedStatuses === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($normalizedStatuses), '?'));

        return (clone $query)
            ->whereRaw("LOWER(COALESCE({$column}, '')) IN ({$placeholders})", $normalizedStatuses)
            ->count();
    }
}