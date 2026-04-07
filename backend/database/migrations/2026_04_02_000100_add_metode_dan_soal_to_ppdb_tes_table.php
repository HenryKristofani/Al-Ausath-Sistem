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
        Schema::table('ppdb_tes', function (Blueprint $table) {
            $table->string('metode_tes', 50)->nullable()->after('status_tes');
            $table->text('soal_tes')->nullable()->after('metode_tes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_tes', function (Blueprint $table) {
            $table->dropColumn(['metode_tes', 'soal_tes']);
        });
    }
};
