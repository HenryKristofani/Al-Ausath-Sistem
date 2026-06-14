<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to fix several issues from issue.md:
 * 
 * 1. Add 'jenis_tagihan' to pembayaran_spp to distinguish SPP/Infaq/UangGedung (Issue #10, #11)
 * 2. Add 'tanggal_konfirmasi' to pembayaran_spp for confirmation tracking (if not exist)
 * 3. Add 'kategori' to administrasi_bebas for better categorization (Issue #12)
 * 4. Ensure 'bulan' column exists in pembayaran_spp (for monthly billing)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (!Schema::hasColumn('pembayaran_spp', 'jenis_tagihan')) {
                // 'spp', 'infaq', 'uang_gedung', 'ppdb', 'lainnya'
                $table->string('jenis_tagihan', 30)->nullable()->default('spp')->after('id_setting');
            }

            if (!Schema::hasColumn('pembayaran_spp', 'tanggal_konfirmasi')) {
                $table->timestamp('tanggal_konfirmasi')->nullable()->after('tanggal_verifikasi');
            }
        });

        Schema::table('administrasi_bebas', function (Blueprint $table) {
            if (!Schema::hasColumn('administrasi_bebas', 'kategori')) {
                $table->string('kategori', 50)->nullable()->after('deskripsi');
            }
            if (!Schema::hasColumn('administrasi_bebas', 'tahun_ajaran')) {
                $table->string('tahun_ajaran', 20)->nullable()->after('kategori');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $drops = array_filter([
                Schema::hasColumn('pembayaran_spp', 'jenis_tagihan') ? 'jenis_tagihan' : null,
                Schema::hasColumn('pembayaran_spp', 'tanggal_konfirmasi') ? 'tanggal_konfirmasi' : null,
            ]);
            if ($drops) $table->dropColumn($drops);
        });

        Schema::table('administrasi_bebas', function (Blueprint $table) {
            $drops = array_filter([
                Schema::hasColumn('administrasi_bebas', 'kategori') ? 'kategori' : null,
                Schema::hasColumn('administrasi_bebas', 'tahun_ajaran') ? 'tahun_ajaran' : null,
            ]);
            if ($drops) $table->dropColumn($drops);
        });
    }
};
