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
        if (! Schema::hasColumn('data_nilai_siswa', 'nilai_akhir')) {
            return;
        }

        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->dropColumn('nilai_akhir');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('data_nilai_siswa', 'nilai_akhir')) {
            return;
        }

        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->decimal('nilai_akhir', 5, 2)->nullable()->after('nilai_uas');
        });
    }
};
