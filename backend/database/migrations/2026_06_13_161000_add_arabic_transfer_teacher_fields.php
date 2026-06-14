<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppdb_tes_konfigurasi', function (Blueprint $table) {
            if (!Schema::hasColumn('ppdb_tes_konfigurasi', 'bahasa')) {
                $table->string('bahasa', 10)->default('id')->after('fitur_soal_aktif');
            }
            if (!Schema::hasColumn('ppdb_tes_konfigurasi', 'is_rtl')) {
                $table->boolean('is_rtl')->default(false)->after('bahasa');
            }
        });

        Schema::table('data_santri', function (Blueprint $table) {
            if (!Schema::hasColumn('data_santri', 'is_pindahan')) {
                $table->boolean('is_pindahan')->default(false)->after('status');
            }
        });

        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            if (!Schema::hasColumn('ppdb_pendaftar', 'bukti_ortu_guru_path')) {
                $table->text('bukti_ortu_guru_path')->nullable()->after('is_anak_guru');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'bukti_ortu_guru_verified')) {
                $table->boolean('bukti_ortu_guru_verified')->default(false)->after('bukti_ortu_guru_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ppdb_tes_konfigurasi', function (Blueprint $table) {
            $drops = array_filter([
                Schema::hasColumn('ppdb_tes_konfigurasi', 'bahasa') ? 'bahasa' : null,
                Schema::hasColumn('ppdb_tes_konfigurasi', 'is_rtl') ? 'is_rtl' : null,
            ]);
            if ($drops) $table->dropColumn($drops);
        });

        Schema::table('data_santri', function (Blueprint $table) {
            if (Schema::hasColumn('data_santri', 'is_pindahan')) {
                $table->dropColumn('is_pindahan');
            }
        });

        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $drops = array_filter([
                Schema::hasColumn('ppdb_pendaftar', 'bukti_ortu_guru_path') ? 'bukti_ortu_guru_path' : null,
                Schema::hasColumn('ppdb_pendaftar', 'bukti_ortu_guru_verified') ? 'bukti_ortu_guru_verified' : null,
            ]);
            if ($drops) $table->dropColumn($drops);
        });
    }
};
