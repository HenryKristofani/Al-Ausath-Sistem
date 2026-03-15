<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('bobot_nilai')->update([
            'jenjang' => 'GLOBAL',
            'kode_unit' => null,
            'bobot_harian' => 20,
            'bobot_uts' => 30,
            'bobot_uas' => 50,
            'bobot_kehadiran' => 0,
            'updated_at' => now(),
        ]);

        DB::table('kkm_mapel')->update([
            'jenjang' => 'GLOBAL',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data policy migration: no automatic rollback to previous variant.
    }
};
