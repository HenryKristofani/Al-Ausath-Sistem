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
        Schema::table('pengumuman', function (Blueprint $table) {
            $table->string('lampiran_path')->nullable()->after('konten');
            $table->string('lampiran_nama_asli')->nullable()->after('lampiran_path');
            $table->string('lampiran_mime')->nullable()->after('lampiran_nama_asli');
            $table->unsignedBigInteger('lampiran_size')->nullable()->after('lampiran_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengumuman', function (Blueprint $table) {
            $table->dropColumn([
                'lampiran_path',
                'lampiran_nama_asli',
                'lampiran_mime',
                'lampiran_size',
            ]);
        });
    }
};
