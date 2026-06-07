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
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            if (!Schema::hasColumn('ppdb_pendaftar', 'pilihan_uang_gedung')) {
                $table->integer('pilihan_uang_gedung')->nullable()->after('is_anak_guru');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'pilihan_infaq_bulanan')) {
                $table->integer('pilihan_infaq_bulanan')->nullable()->after('pilihan_uang_gedung');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            Schema::dropColumns('ppdb_pendaftar', ['pilihan_uang_gedung', 'pilihan_infaq_bulanan']);
        });
    }
};
