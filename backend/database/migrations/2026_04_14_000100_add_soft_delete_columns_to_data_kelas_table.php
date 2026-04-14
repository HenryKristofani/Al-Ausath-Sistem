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
        Schema::table('data_kelas', function (Blueprint $table) {
            if (!Schema::hasColumn('data_kelas', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('status_ppdb');
            }

            if (!Schema::hasColumn('data_kelas', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }

            $table->index('is_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_kelas', function (Blueprint $table) {
            if (Schema::hasColumn('data_kelas', 'is_deleted')) {
                $table->dropIndex(['is_deleted']);
                $table->dropColumn('is_deleted');
            }

            if (Schema::hasColumn('data_kelas', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
