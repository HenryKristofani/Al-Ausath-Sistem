<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_santri', function (Blueprint $table) {
            if (!Schema::hasColumn('data_santri', 'is_anak_guru')) {
                $table->boolean('is_anak_guru')->default(false)->after('nama_wali');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_santri', function (Blueprint $table) {
            if (Schema::hasColumn('data_santri', 'is_anak_guru')) {
                $table->dropColumn('is_anak_guru');
            }
        });
    }
};
