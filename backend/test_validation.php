<?php

use Illuminate\Support\Facades\Validator;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$rules = [
    'id_unit' => ['nullable', 'integer', 'exists:data_unit,id_unit'],
    'id_golongan_spp' => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
    'kode_kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
    'kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
    'jenjang' => ['nullable', 'string', 'max:20'],
    'kategori_tagihan_id' => ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
    'jumlah' => ['nullable', 'numeric'],
    'periode' => ['nullable', 'string', 'max:20'],
    'keterangan' => ['nullable', 'string'],
    'aktif' => ['nullable', 'boolean'],
];

$datasets = [
    'all_null_values' => [
        'id_unit' => null,
        'jenjang' => null,
        'kategori_tagihan_id' => null,
        'periode' => null,
    ],
    'string_null_values' => [
        'id_unit' => 'null',
        'jenjang' => 'null',
        'kategori_tagihan_id' => 'null',
        'periode' => 'null',
    ],
    'empty_string_values' => [
        'id_unit' => '',
        'jenjang' => '',
        'kategori_tagihan_id' => '',
        'periode' => '',
    ],
];

foreach ($datasets as $name => $data) {
    echo "Testing dataset: $name\n";
    $validator = Validator::make($data, $rules);
    if ($validator->fails()) {
        print_r($validator->errors()->toArray());
    } else {
        echo "VALIDATION PASSED\n";
    }
    echo "--------------------\n";
}
