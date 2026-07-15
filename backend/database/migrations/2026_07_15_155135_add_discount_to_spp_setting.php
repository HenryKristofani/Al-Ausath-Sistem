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
        Schema::table('spp_setting', function (Blueprint $table) {
            $table->decimal('discount', 8,2)->default(0)->after('spp_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spp_setting', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
