<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_raport', function (Blueprint $table) {
            $table->string('keseharian_kelakuan', 1)->nullable();
            $table->string('keseharian_kerajinan', 1)->nullable();
            $table->string('keseharian_kedisiplinan', 1)->nullable();
            $table->string('keseharian_ketaatan', 1)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_raport', function (Blueprint $table) {
            $table->dropColumn([
                'keseharian_kelakuan',
                'keseharian_kerajinan',
                'keseharian_kedisiplinan',
                'keseharian_ketaatan',
            ]);
        });
    }
};
