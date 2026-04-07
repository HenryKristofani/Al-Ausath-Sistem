<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('ppdb_pendaftar', 'tanggal_pengumuman')) {
            Schema::table('ppdb_pendaftar', function (Blueprint $table) {
                $table->date('tanggal_pengumuman')->nullable()->after('tanggal_daftar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ppdb_pendaftar', 'tanggal_pengumuman')) {
            Schema::table('ppdb_pendaftar', function (Blueprint $table) {
                $table->dropColumn('tanggal_pengumuman');
            });
        }
    }
};
