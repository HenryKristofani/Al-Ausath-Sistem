<?php

namespace App\Observers;

use App\Models\SppSetting;
use App\Models\DataSantri;
use App\Support\SppBillingService;
use Illuminate\Support\Facades\Log;

/**
 * Observer untuk SppSetting.
 * Trigger provisioning otomatis saat setting diaktifkan atau diupdate.
 * Ini memastikan bills langsung dibuat tanpa menunggu scheduled job.
 */
class SppSettingObserver
{
    /**
     * Handle the SppSetting "created" event.
     */
    public function created(SppSetting $setting): void
    {
        // Jika setting baru langsung aktif, provision bills immediately
        if ($setting->aktif) {
            $this->provisionBillsForSetting($setting);
        }
    }

    /**
     * Handle the SppSetting "updated" event.
     */
    public function updated(SppSetting $setting): void
    {
        // Jika setting baru diaktifkan, provision bills untuk santri yang sesuai
        if ($setting->wasChanged('aktif') && $setting->aktif) {
            $this->provisionBillsForSetting($setting);
        }

        // Jika jumlah (nominal) berubah, update nominal_bayar untuk tagihan yang masih menunggu pembayaran
        if ($setting->wasChanged('jumlah')) {
            $pendingBills = \App\Models\PembayaranSpp::where('id_setting', $setting->id_setting)
                ->where('status', 'menunggu_pembayaran')
                ->with('santri')
                ->get();

            foreach ($pendingBills as $bill) {
                $nominal = (float) $setting->jumlah;
                if ($bill->santri && $bill->santri->is_anak_guru) {
                    $nominal = (float) ($nominal * 0.5);
                }
                $bill->update(['nominal_bayar' => $nominal]);
            }
            Log::info("SPP bills nominal updated for setting {$setting->id_setting} to: {$setting->jumlah}");
        }
    }

    /**
     * Provision bills untuk santri yang sesuai dengan setting.
     * 
     * Strategi provisioning:
     * 1. Jika setting spesifik untuk santri (id_santri) → provision untuk santri itu
     * 2. Jika setting untuk kelas (kode_kelas) → provision untuk semua santri aktif di kelas
     * 3. Jika setting untuk unit (id_unit) → provision untuk semua santri aktif di unit
     * 4. Jika setting untuk jenjang → provision untuk semua santri aktif di jenjang
     * 5. Jika setting untuk golongan SPP → provision untuk semua santri di golongan
     */
    private function provisionBillsForSetting(SppSetting $setting): void
    {
        try {
            $billingService = app(SppBillingService::class);

            // Priority 1: Spesifik santri
            if ($setting->id_santri) {
                $santri = DataSantri::find($setting->id_santri);
                if ($santri) {
                    $billingService->provisionBillingForActiveSantri($santri);
                    Log::info("SPP bills provisioned for specific santri: {$santri->id_santri}");
                }
                return;
            }

            // Priority 2-5: Provision untuk multiple santri
            $query = DataSantri::query()
                ->with(['kelas.unit'])
                ->where('is_deleted', false)
                ->whereRaw('UPPER(status) = ?', ['AKTIF']);

            // Filter berdasarkan setting
            if ($setting->kode_kelas) {
                $query->where('kode_kelas', $setting->kode_kelas);
            } elseif ($setting->id_unit) {
                $query->whereHas('kelas.unit', fn ($q) => $q->where('id_unit', $setting->id_unit));
            } elseif ($setting->jenjang) {
                $query->whereHas('kelas.unit', fn ($q) => 
                    $q->whereRaw('UPPER(nama_unit) = ?', [strtoupper($setting->jenjang)])
                     ->orWhereRaw('UPPER(kode_unit) = ?', [strtoupper($setting->jenjang)])
                );
            } elseif ($setting->id_golongan_spp) {
                $query->where('id_golongan_spp', $setting->id_golongan_spp);
            }

            $santriList = $query->get();
            
            foreach ($santriList as $santri) {
                $billingService->provisionBillingForActiveSantri($santri);
            }

            Log::info("SPP bills provisioned for " . $santriList->count() . " santri from setting {$setting->id_setting}");
        } catch (\Exception $e) {
            Log::error("Error provisioning bills for SPP setting {$setting->id_setting}: " . $e->getMessage());
        }
    }
}
