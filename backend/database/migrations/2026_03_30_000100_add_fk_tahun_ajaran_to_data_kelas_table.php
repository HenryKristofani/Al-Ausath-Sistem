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
        // Normalize values so existing rows can be matched before adding FK.
        DB::table('data_kelas')->update([
            'tahun_ajaran' => DB::raw('TRIM(tahun_ajaran)'),
        ]);

        DB::table('data_tahun_ajaran')->update([
            'kode_tahun' => DB::raw('TRIM(kode_tahun)'),
        ]);

        $missingYears = DB::table('data_kelas')
            ->whereNotNull('tahun_ajaran')
            ->whereRaw("TRIM(tahun_ajaran) <> ''")
            ->whereNotIn('tahun_ajaran', function ($query) {
                $query->select('kode_tahun')->from('data_tahun_ajaran');
            })
            ->distinct()
            ->pluck('tahun_ajaran');

        foreach ($missingYears as $kodeTahun) {
            DB::table('data_tahun_ajaran')->insert([
                'kode_tahun' => $kodeTahun,
                'nama_tahun' => $kodeTahun,
                'status' => 'AKTIF',
                'is_deleted' => false,
            ]);
        }

        Schema::table('data_kelas', function (Blueprint $table) {
            $table->foreign('tahun_ajaran', 'fk_data_kelas_tahun_ajaran')
                ->references('kode_tahun')
                ->on('data_tahun_ajaran')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_kelas', function (Blueprint $table) {
            $table->dropForeign('fk_data_kelas_tahun_ajaran');
        });
    }
};
