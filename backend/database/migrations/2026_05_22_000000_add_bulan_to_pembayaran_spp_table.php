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
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (!Schema::hasColumn('pembayaran_spp', 'bulan')) {
                $table->string('bulan', 20)->nullable()->after('id_setting');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (Schema::hasColumn('pembayaran_spp', 'bulan')) {
                $table->dropColumn('bulan');
            }
        });
    }
};
