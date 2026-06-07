<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            if (!Schema::hasColumn('ppdb_pendaftar', 'is_anak_guru')) {
                $table->boolean('is_anak_guru')->default(false)->after('status_verifikasi');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'tanggal_diterima')) {
                $table->date('tanggal_diterima')->nullable()->after('is_anak_guru');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'batas_bayar_uang_pangkal')) {
                $table->date('batas_bayar_uang_pangkal')->nullable()->after('tanggal_diterima');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'batas_bayar_spp')) {
                $table->date('batas_bayar_spp')->nullable()->after('batas_bayar_uang_pangkal');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'status_uang_pangkal')) {
                // Values: null, 'menunggu', 'dp', 'lunas', 'gagal'
                $table->string('status_uang_pangkal', 20)->nullable()->after('batas_bayar_spp');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'status_spp')) {
                // Values: null, 'menunggu', 'dp', 'lunas', 'gagal'
                $table->string('status_spp', 20)->nullable()->after('status_uang_pangkal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('ppdb_pendaftar', 'is_anak_guru') ? 'is_anak_guru' : null,
                Schema::hasColumn('ppdb_pendaftar', 'batas_bayar_uang_pangkal') ? 'batas_bayar_uang_pangkal' : null,
                Schema::hasColumn('ppdb_pendaftar', 'batas_bayar_spp') ? 'batas_bayar_spp' : null,
                Schema::hasColumn('ppdb_pendaftar', 'status_uang_pangkal') ? 'status_uang_pangkal' : null,
                Schema::hasColumn('ppdb_pendaftar', 'status_spp') ? 'status_spp' : null,
            ]);

            if (count($columns) > 0) {
                $table->dropColumn($columns);
            }
        });
    }
};
