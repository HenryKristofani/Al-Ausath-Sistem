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
        Schema::table('data_santri', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('nama_wali');
            $table->timestamp('deleted_at')->nullable()->after('is_deleted');
            $table->index('is_deleted', 'data_santri_is_deleted_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_santri', function (Blueprint $table) {
            $table->dropIndex('data_santri_is_deleted_index');
            $table->dropColumn(['is_deleted', 'deleted_at']);
        });
    }
};