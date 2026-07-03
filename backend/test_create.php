<?php
$model = \App\Models\ProfilWeb::create(['tipe' => 'TKA', 'nama' => 'TK A', 'visi' => '', 'misi' => [], 'sejarah' => '', 'program_unggulan' => []]);
echo $model->tipe;
