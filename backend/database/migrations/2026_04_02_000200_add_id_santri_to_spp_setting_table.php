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
            $table->integer('id_santri')->nullable()->after('id_unit');
            $table->index('id_santri');
            $table->foreign('id_santri')
                ->references('id_santri')
                ->on('data_santri')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spp_setting', function (Blueprint $table) {
            $table->dropForeign(['id_santri']);
            $table->dropIndex(['id_santri']);
            $table->dropColumn('id_santri');
        });
    }
};
