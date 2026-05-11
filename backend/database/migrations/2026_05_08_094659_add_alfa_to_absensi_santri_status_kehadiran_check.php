<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop the old check constraint on absensi_santri.status_kehadiran
     * and recreate it to include 'ALFA'.
     */
    public function up(): void
    {
        // Drop constraint lama yang tidak mengizinkan ALFA
        DB::statement('ALTER TABLE absensi_santri DROP CONSTRAINT IF EXISTS absensi_santri_status_kehadiran_check');

        // Recreate constraint baru dengan ALFA included
        DB::statement("
            ALTER TABLE absensi_santri
            ADD CONSTRAINT absensi_santri_status_kehadiran_check
            CHECK (status_kehadiran IN ('HADIR', 'IZIN', 'SAKIT', 'ALFA'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke constraint tanpa ALFA
        DB::statement('ALTER TABLE absensi_santri DROP CONSTRAINT IF EXISTS absensi_santri_status_kehadiran_check');

        DB::statement("
            ALTER TABLE absensi_santri
            ADD CONSTRAINT absensi_santri_status_kehadiran_check
            CHECK (status_kehadiran IN ('HADIR', 'IZIN', 'SAKIT'))
        ");
    }
};
