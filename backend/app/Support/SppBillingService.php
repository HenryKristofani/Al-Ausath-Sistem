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
     * 
     * LOGIC:
     * - Hanya process santri yang aktif dan tidak deleted
     * - Match SppSetting berdasarkan priority (santri > kelas > unit > jenjang > golongan)
     * - Hanya create PembayaranSpp jika belum ada (idempotent)
     * - Status default: 'menunggu_pembayaran'
     * - Nominal diambil dari SppSetting.jumlah
     */
    public function provisionBillingForActiveSantri(DataSantri $santri): void
    {
        // Early exit: skip if santri inactive
        if ($santri->is_deleted || strtoupper((string) $santri->status) !== 'AKTIF') {
            Log::debug("Skipping santri {$santri->id_santri}: deleted={$santri->is_deleted}, status={$santri->status}");
            return;
        }

        $santri->loadMissing(['kelas.unit']);

        // Get active academic year
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

        // Match SppSetting dengan priority
        $settings = SppSetting::query()
            ->with(['kategoriTagihan']) // Eager load kategoriTagihan
            ->where('aktif', true) // CRITICAL: Only process aktif settings
            ->where(function ($query) use ($santri) {
                // Priority 1: Spesifik santri
                $query->where('id_santri', $santri->id_santri);

                // Priority 2: Spesifik kelas
                $query->orWhere('kode_kelas', $santri->kode_kelas);

                // Priority 3: Spesifik unit
                if ($santri->kelas?->unit?->id_unit) {
                    $query->orWhere('id_unit', $santri->kelas->unit->id_unit);
                }

                // Priority 4: By jenjang (derived from unit or class)
                $jenjang = strtoupper(trim((string) ($santri->kelas?->unit?->nama_unit ?? $santri->kelas?->unit?->kode_unit ?? '')));
                if ($jenjang) {
                    $query->orWhere('jenjang', $jenjang);
                }

                // Priority 5: SPP golongan
                if (!empty($santri->id_golongan_spp)) {
                    $query->orWhere('id_golongan_spp', $santri->id_golongan_spp);
                }
            })
            ->when($periodCandidates->isNotEmpty(), function ($query) use ($periodCandidates) {
                $query->where(function ($periodQuery) use ($periodCandidates) {
                    foreach ($periodCandidates as $period) {
                        $periodQuery->orWhere('periode', $period);
                        
                        // Handle year-only matches
                        if (preg_match('/\d{4}/', $period, $matches)) {
                            $year = $matches[0];
                            $periodQuery->orWhere('periode', 'like', "%{$year}%");
                        }
                    }
                });
            })
            ->get();

        Log::info("Provisioning SPP for santri {$santri->id_santri}. Found " 
            . $settings->count() . " active settings. Period candidates: " 
            . $periodCandidates->implode(', '));

        // Create PembayaranSpp records (idempotent via firstOrCreate)
        $createdCount = 0;
        $totalProcessedCount = 0;
        foreach ($settings as $setting) {
            $isSpp = false;
            if ($setting->kategoriTagihan) {
                $namaTagihan = strtolower($setting->kategoriTagihan->nama_tagihan);
                if (strpos($namaTagihan, 'spp') !== false) {
                    $isSpp = true;
                }
            } else {
                $keterangan = strtolower($setting->keterangan ?? '');
                if (strpos($keterangan, 'spp') !== false) {
                    $isSpp = true;
                }
            }

            if ($isSpp) {
                // If it is SPP, it should be calculated per month (6 months in a semester)
                $periodUpper = strtoupper($setting->periode ?? '');
                if (strpos($periodUpper, 'GENAP') !== false) {
                    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
                } else {
                    // Default to GANJIL / standard first semester
                    $months = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                }

                foreach ($months as $month) {
                    $totalProcessedCount++;
                    $created = PembayaranSpp::firstOrCreate(
                        [
                            'id_santri' => $santri->id_santri,
                            'id_setting' => $setting->id_setting,
                            'id_pendaftaran' => null,
                            'bulan' => $month,
                        ],
                        [
                            'nominal_bayar' => (float) ($setting->jumlah ?? 0),
                            'tanggal_bayar' => null,
                            'metode_bayar' => null,
                            'status' => 'menunggu_pembayaran',
                        ]
                    );

                    if ($created->wasRecentlyCreated) {
                        $createdCount++;
                    }
                }
            } else {
                $totalProcessedCount++;
                // Non-SPP (e.g. one-off fees)
                $created = PembayaranSpp::firstOrCreate(
                    [
                        'id_santri' => $santri->id_santri,
                        'id_setting' => $setting->id_setting,
                        'id_pendaftaran' => null,
                        'bulan' => null,
                    ],
                    [
                        'nominal_bayar' => (float) ($setting->jumlah ?? 0),
                        'tanggal_bayar' => null,
                        'metode_bayar' => null,
                        'status' => 'menunggu_pembayaran',
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $createdCount++;
                }
            }
        }

        Log::info("Provisioning completed for santri {$santri->id_santri}. Created {$createdCount} new bills, skipped " 
            . ($totalProcessedCount - $createdCount) . " existing bills.");
    }
}
