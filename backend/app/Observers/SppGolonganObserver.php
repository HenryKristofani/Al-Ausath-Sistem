<?php

namespace App\Observers;

use App\Models\SppGolongan;
use App\Models\SppSetting;
use Illuminate\Support\Facades\Log;

class SppGolonganObserver
{
    /**
     * Handle the SppGolongan "updated" event.
     */
    public function updated(SppGolongan $golongan): void
    {
        if ($golongan->wasChanged('nominal')) {
            Log::info("SppGolongan nominal updated for golongan {$golongan->id_golongan} to: {$golongan->nominal}. Propagating to settings.");

            // Find settings referencing this golongan and update their jumlah
            $settings = SppSetting::where('id_golongan_spp', $golongan->id_golongan)->get();
            foreach ($settings as $setting) {
                // This update triggers SppSettingObserver@updated, which in turn updates unpaid bills
                $setting->update(['jumlah' => $golongan->nominal]);
            }
        }
    }
}
