<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PpdbPendaftar;
use App\Models\PembayaranSpp;
use App\Models\DataSantri;
use App\Http\Controllers\Api\Administrasi\PpdbController;

$pendaftars = PpdbPendaftar::where('status_verifikasi', 'diterima')->get();

$controller = app(PpdbController::class);

echo "Found " . $pendaftars->count() . " accepted pendaftars.\n";

foreach ($pendaftars as $p) {
    echo "Processing pendaftar ID: {$p->id_pendaftaran}, Name: {$p->nama_calon}, Jenjang: {$p->jenjang}, Santri ID: {$p->id_santri}\n";
    
    // 1. Create PPDB Uang Pangkal/Modul bills
    $method1 = new ReflectionMethod(PpdbController::class, 'createTagihanPpdbIfNeeded');
    $method1->setAccessible(true);
    $tagihanPpdb = $method1->invoke($controller, $p);
    if ($tagihanPpdb) {
        echo "  -> Created/Found PPDB tagihan: ID={$tagihanPpdb->id_pembayaran}, Nominal={$tagihanPpdb->nominal_bayar}, Status={$tagihanPpdb->status}, Jenis={$tagihanPpdb->jenis_tagihan}\n";
    } else {
        echo "  -> No PPDB tagihan created.\n";
    }

    // 2. Create Infaq Bulanan bill
    $santri = $p->id_santri ? DataSantri::find($p->id_santri) : null;
    if ($santri) {
        $method2 = new ReflectionMethod(PpdbController::class, 'createTagihanInfaqIfNeeded');
        $method2->setAccessible(true);
        $tagihanInfaq = $method2->invoke($controller, $p, $santri);
        if ($tagihanInfaq) {
            echo "  -> Created/Found Infaq tagihan: ID={$tagihanInfaq->id_pembayaran}, Nominal={$tagihanInfaq->nominal_bayar}, Status={$tagihanInfaq->status}, Jenis={$tagihanInfaq->jenis_tagihan}\n";
        } else {
            echo "  -> No Infaq tagihan created.\n";
        }
    } else {
        echo "  -> No linked Santri, skipping Infaq Bulanan tagihan.\n";
    }
    
    // 3. Ensure id_santri is correctly linked on all bills
    if ($p->id_santri) {
        $updated = PembayaranSpp::where('id_pendaftaran', $p->id_pendaftaran)
            ->whereNull('id_santri')
            ->update(['id_santri' => $p->id_santri]);
        if ($updated > 0) {
            echo "  -> Linked {$updated} unlinked bills to Santri ID={$p->id_santri}\n";
        }
    }
}
