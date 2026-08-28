<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom artikel_url (single string) ke tabel profil_web.
     * Kolom ini menyimpan URL website artikel pesantren yang sudah ada,
     * sehingga admin bisa mengubahnya tanpa perlu coding.
     */
    public function up(): void
    {
        Schema::table('profil_web', function (Blueprint $table) {
            $table->string('artikel_url', 500)->nullable()->after('artikel_links');
        });
    }

    public function down(): void
    {
        Schema::table('profil_web', function (Blueprint $table) {
            $table->dropColumn('artikel_url');
        });
    }
};
