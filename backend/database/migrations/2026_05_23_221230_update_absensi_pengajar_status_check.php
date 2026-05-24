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
        DB::statement('ALTER TABLE absensi_pengajar DROP CONSTRAINT IF EXISTS absensi_pengajar_status_kehadiran_check;');
        DB::statement("ALTER TABLE absensi_pengajar ADD CONSTRAINT absensi_pengajar_status_kehadiran_check CHECK (status_kehadiran::text = ANY (ARRAY['HADIR'::character varying, 'SAKIT'::character varying, 'IZIN'::character varying, 'ALFA'::character varying]::text[]));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE absensi_pengajar DROP CONSTRAINT IF EXISTS absensi_pengajar_status_kehadiran_check;');
        DB::statement("ALTER TABLE absensi_pengajar ADD CONSTRAINT absensi_pengajar_status_kehadiran_check CHECK (status_kehadiran::text = ANY (ARRAY['HADIR'::character varying, 'SAKIT'::character varying, 'IZIN'::character varying]::text[]));");
    }
};
