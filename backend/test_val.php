<?php
$v = Validator::make(['tipe' => 'TK', 'nama' => 'TK', 'visi' => '', 'misi' => [], 'sejarah' => '', 'program_unggulan' => []], [
    'tipe' => 'required|string|unique:profil_web,tipe',
    'nama' => 'required|string',
    'visi' => 'nullable|string',
    'misi' => 'nullable|array',
    'sejarah' => 'nullable|string',
    'program_unggulan' => 'nullable|array',
]);
if ($v->fails()) {
    echo json_encode($v->errors());
} else {
    echo 'OK';
}
