<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fix untuk Neon Database error "25P02"
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_raport', function (Blueprint $table) {
            if (!Schema::hasColumn('data_raport', 'ekstrakurikuler')) {
                $table->json('ekstrakurikuler')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_raport', function (Blueprint $table) {
            if (Schema::hasColumn('data_raport', 'ekstrakurikuler')) {
                $table->dropColumn('ekstrakurikuler');
            }
        });
    }
};
