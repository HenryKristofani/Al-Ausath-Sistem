<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PembayaranSpp;
use App\Models\PpdbPendaftar;

$pIds = PpdbPendaftar::where('jenjang', 'MI')->pluck('id_pendaftaran');
$payments = PembayaranSpp::whereIn('id_pendaftaran', $pIds)->get();
echo "MI payments: " . $payments->count() . "\n";
echo $payments->toJson(JSON_PRETTY_PRINT) . "\n";
