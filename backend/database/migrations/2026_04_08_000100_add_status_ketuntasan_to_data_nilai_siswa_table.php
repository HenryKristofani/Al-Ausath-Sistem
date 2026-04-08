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
        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->string('status_ketuntasan', 20)->nullable()->after('flag_warna_rapor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->dropColumn('status_ketuntasan');
        });
    }
};