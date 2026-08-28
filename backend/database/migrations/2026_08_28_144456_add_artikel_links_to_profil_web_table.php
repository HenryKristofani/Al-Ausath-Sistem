<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tambah kolom artikel_links (JSON) ke tabel profil_web.
     * Format: [{ judul, deskripsi, url, ikon }]
     */
    public function up(): void
    {
        Schema::table('profil_web', function (Blueprint $table) {
            $table->json('artikel_links')->nullable()->after('fasilitas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profil_web', function (Blueprint $table) {
            $table->dropColumn('artikel_links');
        });
    }
};
