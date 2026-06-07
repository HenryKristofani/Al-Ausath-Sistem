<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            if (!Schema::hasColumn('ppdb_pendaftar', 'bukti_uang_pangkal_path')) {
                $table->string('bukti_uang_pangkal_path')->nullable()->after('status_uang_pangkal');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'bukti_spp_path')) {
                $table->string('bukti_spp_path')->nullable()->after('status_spp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('ppdb_pendaftar', 'bukti_uang_pangkal_path') ? 'bukti_uang_pangkal_path' : null,
                Schema::hasColumn('ppdb_pendaftar', 'bukti_spp_path') ? 'bukti_spp_path' : null,
            ]);

            if (count($columns) > 0) {
                $table->dropColumn($columns);
            }
        });
    }
};
