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
        Schema::table('nilai_akhlak', function (Blueprint $table) {
            $table->decimal('nilai_angka', 5, 2)->nullable()->after('aspek');
        });

        Schema::table('data_raport', function (Blueprint $table) {
            $table->string('keseharian_kebersihan', 1)->nullable()->after('alpha');
            $table->string('keseharian_kerapian', 1)->nullable()->after('keseharian_kebersihan');
            $table->string('keseharian_keterampilan', 1)->nullable()->after('keseharian_kerapian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai_akhlak', function (Blueprint $table) {
            $table->dropColumn('nilai_angka');
        });

        Schema::table('data_raport', function (Blueprint $table) {
            $table->dropColumn([
                'keseharian_kebersihan',
                'keseharian_kerapian',
                'keseharian_keterampilan',
            ]);
        });
    }
};
