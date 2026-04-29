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
        Schema::table('spp_golongan', function (Blueprint $table) {
            if (!Schema::hasColumn('spp_golongan', 'nominal')) {
                $table->decimal('nominal', 15, 2)->default(0)->after('jenjang');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spp_golongan', function (Blueprint $table) {
            if (Schema::hasColumn('spp_golongan', 'nominal')) {
                $table->dropColumn('nominal');
            }
        });
    }
};
