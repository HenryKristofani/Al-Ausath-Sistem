<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_kelas', function (Blueprint $table) {
            // id_petugas wali kelas, nullable (opsional)
            $table->unsignedBigInteger('id_wali_kelas')->nullable()->after('status_ppdb');

            // Foreign key ke data_petugas
            $table->foreign('id_wali_kelas')
                ->references('id_petugas')
                ->on('data_petugas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_kelas', function (Blueprint $table) {
            $table->dropForeign(['id_wali_kelas']);
            $table->dropColumn('id_wali_kelas');
        });
    }
};
