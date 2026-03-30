<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kkm_mapel', function (Blueprint $table) {
            $table->dropUnique('uniq_kkm_mapel_jenjang_tahun_semester');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX uniq_kkm_mapel_global ON kkm_mapel (kode_mapel, jenjang, tahun_ajaran, semester) WHERE kode_unit IS NULL'
            );

            DB::statement(
                'CREATE UNIQUE INDEX uniq_kkm_mapel_per_unit ON kkm_mapel (kode_mapel, jenjang, kode_unit, tahun_ajaran, semester) WHERE kode_unit IS NOT NULL'
            );

            return;
        }

        Schema::table('kkm_mapel', function (Blueprint $table) {
            $table->unique(['kode_mapel', 'jenjang', 'kode_unit', 'tahun_ajaran', 'semester'], 'uniq_kkm_mapel_per_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS uniq_kkm_mapel_per_unit');
            DB::statement('DROP INDEX IF EXISTS uniq_kkm_mapel_global');
        } else {
            Schema::table('kkm_mapel', function (Blueprint $table) {
                $table->dropUnique('uniq_kkm_mapel_per_unit');
            });
        }

        Schema::table('kkm_mapel', function (Blueprint $table) {
            $table->unique(['kode_mapel', 'jenjang', 'tahun_ajaran', 'semester'], 'uniq_kkm_mapel_jenjang_tahun_semester');
        });
    }
};
