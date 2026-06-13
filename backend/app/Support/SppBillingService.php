<?php

namespace App\Support;

use App\Models\DataSantri;
use App\Models\DataTahunAjaran;
use App\Models\PembayaranSpp;
use App\Models\SppSetting;
use Illuminate\Support\Facades\Log;

class SppBillingService
{
    protected static $cachedActiveYear = null;
    protected static bool $activeYearLoaded = false;

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
        if (!self::$activeYearLoaded) {
            self::$cachedActiveYear = DataTahunAjaran::query()
                ->whereRaw('UPPER(status) = ?', ['AKTIF'])
                ->where('is_deleted', false)
                ->first();
            self::$activeYearLoaded = true;
        }
        $activeYear = self::$cachedActiveYear;

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

        // Fetch all existing bills for this santri in one query to optimize performance
        $existingBills = PembayaranSpp::where('id_santri', $santri->id_santri)
            ->whereNull('id_pendaftaran')
            ->get();

        // Create PembayaranSpp records (idempotent via in-memory checks)
        $createdCount = 0;
        $totalProcessedCount = 0;
        foreach ($settings as $setting) {
            $isSpp = false;
            $isInfaq = false;
            $isUangGedung = false;
            $namaTagihanLower = '';

            if ($setting->kategoriTagihan) {
                $namaTagihanLower = strtolower($setting->kategoriTagihan->nama_tagihan);
            } else {
                $namaTagihanLower = strtolower($setting->keterangan ?? '');
            }

            if (strpos($namaTagihanLower, 'spp') !== false) {
                $isSpp = true;
            }
            if (strpos($namaTagihanLower, 'infaq') !== false || strpos($namaTagihanLower, 'infak') !== false) {
                $isInfaq = true;
            }
            if (strpos($namaTagihanLower, 'gedung') !== false || strpos($namaTagihanLower, 'pangkal') !== false) {
                $isUangGedung = true;
            }

            // Determine jenis_tagihan
            $jenisTagihan = 'spp';
            if ($isInfaq) {
                $jenisTagihan = 'infaq';
            } elseif ($isUangGedung) {
                $jenisTagihan = 'uang_gedung';
            } elseif (!$isSpp) {
                $jenisTagihan = 'lainnya';
            }

            $nominal = (float) ($setting->jumlah ?? 0);
            if ($santri->is_anak_guru) {
                $nominal = (float) ($nominal * 0.5);
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

                if ($santri->is_pindahan && $santri->created_at) {
                    $entryMonthVal = (int) $santri->created_at->format('m');
                    $entryYearVal = (int) $santri->created_at->format('Y');
                    $periodYear = null;
                    if (preg_match('/(\d{4})/', $periodUpper, $matches)) {
                        $periodYear = (int) $matches[1];
                    }
                    $monthMap = [
                        'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4, 'Mei' => 5, 'Juni' => 6,
                        'Juli' => 7, 'Agustus' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
                    ];
                    $months = array_filter($months, function($mName) use ($monthMap, $entryMonthVal, $entryYearVal, $periodYear) {
                        $mVal = $monthMap[$mName] ?? 1;
                        if ($entryYearVal === $periodYear || ($entryYearVal === $periodYear + 1 && $mVal <= 6)) {
                            return $mVal >= $entryMonthVal;
                        }
                        return true;
                    });
                }

                foreach ($months as $month) {
                    $totalProcessedCount++;

                    // In-memory lookup to bypass database N+1 queries
                    $exists = $existingBills->first(function ($bill) use ($setting, $month) {
                        return $bill->id_setting == $setting->id_setting && $bill->bulan === $month;
                    });

                    if (!$exists) {
                        PembayaranSpp::create([
                            'id_santri' => $santri->id_santri,
                            'id_setting' => $setting->id_setting,
                            'id_pendaftaran' => null,
                            'bulan' => $month,
                            'jenis_tagihan' => $jenisTagihan,
                            'nominal_bayar' => $nominal,
                            'tanggal_bayar' => null,
                            'metode_bayar' => null,
                            'status' => 'menunggu_pembayaran',
                        ]);
                        $createdCount++;
                    }
                }
            } elseif ($isInfaq) {
                // Infaq: per-month billing similar to SPP
                $periodUpper = strtoupper($setting->periode ?? '');
                if (strpos($periodUpper, 'GENAP') !== false) {
                    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
                } else {
                    $months = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                }

                if ($santri->is_pindahan && $santri->created_at) {
                    $entryMonthVal = (int) $santri->created_at->format('m');
                    $entryYearVal = (int) $santri->created_at->format('Y');
                    $periodYear = null;
                    if (preg_match('/(\d{4})/', $periodUpper, $matches)) {
                        $periodYear = (int) $matches[1];
                    }
                    $monthMap = [
                        'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4, 'Mei' => 5, 'Juni' => 6,
                        'Juli' => 7, 'Agustus' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
                    ];
                    $months = array_filter($months, function($mName) use ($monthMap, $entryMonthVal, $entryYearVal, $periodYear) {
                        $mVal = $monthMap[$mName] ?? 1;
                        if ($entryYearVal === $periodYear || ($entryYearVal === $periodYear + 1 && $mVal <= 6)) {
                            return $mVal >= $entryMonthVal;
                        }
                        return true;
                    });
                }

                foreach ($months as $month) {
                    $totalProcessedCount++;

                    // In-memory lookup to bypass database N+1 queries
                    $exists = $existingBills->first(function ($bill) use ($setting, $month) {
                        return $bill->id_setting == $setting->id_setting && $bill->bulan === $month;
                    });

                    if (!$exists) {
                        PembayaranSpp::create([
                            'id_santri' => $santri->id_santri,
                            'id_setting' => $setting->id_setting,
                            'id_pendaftaran' => null,
                            'bulan' => $month,
                            'jenis_tagihan' => 'infaq',
                            'nominal_bayar' => $nominal,
                            'tanggal_bayar' => null,
                            'metode_bayar' => null,
                            'status' => 'menunggu_pembayaran',
                        ]);
                        $createdCount++;
                    }
                }
            } else {
                $totalProcessedCount++;

                // In-memory lookup to bypass database N+1 queries
                $exists = $existingBills->first(function ($bill) use ($setting) {
                    return $bill->id_setting == $setting->id_setting && $bill->bulan === null;
                });

                if (!$exists) {
                    PembayaranSpp::create([
                        'id_santri' => $santri->id_santri,
                        'id_setting' => $setting->id_setting,
                        'id_pendaftaran' => null,
                        'bulan' => null,
                        'jenis_tagihan' => $jenisTagihan,
                        'nominal_bayar' => $nominal,
                        'tanggal_bayar' => null,
                        'metode_bayar' => null,
                        'status' => 'menunggu_pembayaran',
                    ]);
                    $createdCount++;
                }
            }
        }

        Log::info("Provisioning completed for santri {$santri->id_santri}. Created {$createdCount} new bills, skipped " 
            . ($totalProcessedCount - $createdCount) . " existing bills.");
    }
}
