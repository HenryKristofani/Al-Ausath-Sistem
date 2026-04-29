<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_santri', function (Blueprint $table) {
            if (!Schema::hasColumn('data_santri', 'id_golongan_spp')) {
                $table->integer('id_golongan_spp')->nullable()->after('kode_kelas');
                $table->index('id_golongan_spp');
                $table->foreign('id_golongan_spp')
                    ->references('id_golongan')
                    ->on('spp_golongan')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_santri', function (Blueprint $table) {
            if (Schema::hasColumn('data_santri', 'id_golongan_spp')) {
                $table->dropForeign(['id_golongan_spp']);
                $table->dropIndex(['id_golongan_spp']);
                $table->dropColumn('id_golongan_spp');
            }
        });
    }
};
