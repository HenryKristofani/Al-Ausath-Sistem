<?php

namespace App\Support;

use App\Models\DataSantri;
use App\Models\DataTahunAjaran;
use App\Models\PembayaranSpp;
use App\Models\SppSetting;
use Illuminate\Support\Facades\Log;

class SppBillingService
{
    /**
     * Provision tagihan SPP untuk santri aktif secara idempotent.
     */
    public function provisionBillingForActiveSantri(DataSantri $santri): void
    {
        if ($santri->is_deleted || strtoupper((string) $santri->status) !== 'AKTIF') {
            return;
        }

        $santri->loadMissing(['kelas.unit']);

        $activeYear = DataTahunAjaran::query()
            ->whereRaw('UPPER(status) = ?', ['AKTIF'])
            ->where('is_deleted', false)
            ->first();

        $periodCandidates = collect([
            $activeYear?->kode_tahun,
            $activeYear?->nama_tahun,
            $santri->kelas?->tahun_ajaran,
        ])
            ->filter()
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->unique()
            ->values();

        $settings = SppSetting::query()
            ->where(function ($query) use ($santri) {
                $query->where('kode_kelas', $santri->kode_kelas);

                if ($santri->kelas?->unit?->id_unit) {
                    $query->orWhere('id_unit', $santri->kelas->unit->id_unit);
                }

                if (!empty($santri->id_golongan_spp)) {
                    $query->orWhere('id_golongan_spp', $santri->id_golongan_spp);
                }
            })
            ->when($periodCandidates->isNotEmpty(), function ($query) use ($periodCandidates) {
                $query->where(function ($periodQuery) use ($periodCandidates) {
                    foreach ($periodCandidates as $period) {
                        $periodQuery->orWhereRaw('UPPER(periode) = ?', [$period]);
                    }
                });
            })
            ->get();

        Log::info("Provisioning SPP for santri {$santri->id_santri}. Found " . $settings->count() . " settings for periods: " . $periodCandidates->implode(', '));

        foreach ($settings as $setting) {
            PembayaranSpp::firstOrCreate(
                [
                    'id_santri' => $santri->id_santri,
                    'id_setting' => $setting->id_setting,
                    'id_pendaftaran' => null,
                ],
                [
                    'nominal_bayar' => (float) ($setting->jumlah ?? 0),
                    'tanggal_bayar' => null,
                    'metode_bayar' => null,
                    'status' => 'menunggu_pembayaran',
                ]
            );
        }
    }
}
