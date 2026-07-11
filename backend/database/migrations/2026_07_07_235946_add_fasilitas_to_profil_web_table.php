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
        Schema::table('profil_web', function (Blueprint $table) {
            $table->json('fasilitas')->nullable()->after('program_unggulan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profil_web', function (Blueprint $table) {
            $table->dropColumn('fasilitas');
        });
    }
};
