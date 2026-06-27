<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PpdbPendaftar;
use App\Models\PembayaranSpp;
use App\Models\DataSantri;
use App\Http\Controllers\Api\Administrasi\PpdbController;

$p = PpdbPendaftar::where('status_verifikasi', 'diterima')->where('jenjang', 'MI')->first();

if (!$p) {
    echo "No accepted MI pendaftar found!\n";
    exit;
}

echo "Testing for pendaftar ID: {$p->id_pendaftaran}, Name: {$p->nama_calon}, Jenjang: {$p->jenjang}\n";

$controller = app(PpdbController::class);

echo "Calling createTagihanPpdbIfNeeded...\n";
try {
    $method1 = new ReflectionMethod(PpdbController::class, 'createTagihanPpdbIfNeeded');
    $method1->setAccessible(true);
    $tagihanPpdb = $method1->invoke($controller, $p);
    echo "Success PPDB! ID = " . ($tagihanPpdb ? $tagihanPpdb->id_pembayaran : 'null') . "\n";
} catch (\Exception $e) {
    echo "Error PPDB: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "Calling createTagihanInfaqIfNeeded...\n";
try {
    $santri = $p->id_santri ? DataSantri::find($p->id_santri) : null;
    $method2 = new ReflectionMethod(PpdbController::class, 'createTagihanInfaqIfNeeded');
    $method2->setAccessible(true);
    $tagihanInfaq = $method2->invoke($controller, $p, $santri);
    echo "Success Infaq! ID = " . ($tagihanInfaq ? $tagihanInfaq->id_pembayaran : 'null') . "\n";
} catch (\Exception $e) {
    echo "Error Infaq: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
