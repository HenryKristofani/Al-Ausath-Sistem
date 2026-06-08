<?php

namespace App\Observers;

use App\Models\DataSantri;
use App\Models\PembayaranSpp;
use App\Support\SppBillingService;
use Illuminate\Support\Facades\Log;

class DataSantriObserver
{
    /**
     * Handle the DataSantri "updated" event.
     */
    public function updated(DataSantri $santri): void
    {
        // If status changes to inactive or student gets soft-deleted, delete unpaid bills
        if ($santri->wasChanged('status') && strtoupper((string) $santri->status) !== 'AKTIF') {
            Log::info("Santri {$santri->id_santri} status changed to {$santri->status}. Deleting unpaid SPP bills.");
            PembayaranSpp::where('id_santri', $santri->id_santri)
                ->where('status', 'menunggu_pembayaran')
                ->delete();
            return;
        }

        if ($santri->wasChanged('is_deleted') && $santri->is_deleted) {
            Log::info("Santri {$santri->id_santri} was soft deleted. Deleting unpaid SPP bills.");
            PembayaranSpp::where('id_santri', $santri->id_santri)
                ->where('status', 'menunggu_pembayaran')
                ->delete();
            return;
        }

        // If class, SPP golongan, or is_anak_guru status changes, delete unpaid bills and re-provision
        if ($santri->wasChanged('id_golongan_spp') || $santri->wasChanged('kode_kelas') || $santri->wasChanged('is_anak_guru')) {
            Log::info("Santri {$santri->id_santri} changed class or SPP golongan. Re-evaluating unpaid SPP bills.");

            // 1. Delete unpaid bills
            PembayaranSpp::where('id_santri', $santri->id_santri)
                ->where('status', 'menunggu_pembayaran')
                ->delete();

            // 2. Re-provision using billing service
            try {
                $billingService = app(SppBillingService::class);
                $billingService->provisionBillingForActiveSantri($santri);
                Log::info("SPP bills successfully re-provisioned for santri {$santri->id_santri}.");
            } catch (\Exception $e) {
                Log::error("Failed to re-provision bills after santri update: " . $e->getMessage());
            }
        }
    }
}
