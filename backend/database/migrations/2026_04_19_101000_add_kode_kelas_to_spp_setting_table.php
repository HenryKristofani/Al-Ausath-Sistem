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
        Schema::table('spp_setting', function (Blueprint $table) {
            if (!Schema::hasColumn('spp_setting', 'kode_kelas')) {
                $table->string('kode_kelas', 10)->nullable()->after('id_golongan_spp');
                $table->index('kode_kelas');
                $table->foreign('kode_kelas')
                    ->references('kode_kelas')
                    ->on('data_kelas')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spp_setting', function (Blueprint $table) {
            if (Schema::hasColumn('spp_setting', 'kode_kelas')) {
                $table->dropForeign(['kode_kelas']);
                $table->dropIndex(['kode_kelas']);
                $table->dropColumn('kode_kelas');
            }
        });
    }
};
