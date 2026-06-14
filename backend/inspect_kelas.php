<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kelas = \App\Models\DataKelas::get(['kode_kelas', 'nama_kelas', 'status', 'tahun_ajaran']);
echo json_encode($kelas, JSON_PRETTY_PRINT);
