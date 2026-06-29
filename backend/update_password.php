<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DataAkunSantri;
use Illuminate\Support\Facades\Hash;

$a = DataAkunSantri::where('nomor_induk', '3202605')->first();
if ($a) {
    $a->password_hash = Hash::make('password123');
    $a->save();
    echo "Password updated successfully for " . $a->nama_lengkap . "\n";
} else {
    echo "Account not found!\n";
}
