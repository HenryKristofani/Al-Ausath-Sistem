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
        Schema::create('data_raport', function (Blueprint $table) {
        $table->integer('id_raport', true)->primary();
            $table->string('nomor_induk', 20);
            $table->string('kode_kelas', 10);
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->decimal('jumlah_nilai', 8, 2)->default(0);
            $table->decimal('rata_rata', 5, 2)->default(0);
            $table->integer('peringkat_kelas')->nullable();
            $table->integer('total_siswa_kelas')->nullable();
            $table->integer('hadir')->default(0);
            $table->integer('sakit')->default(0);
            $table->integer('izin')->default(0);
            $table->integer('alpha')->default(0);
            $table->string('status_raport', 20)->default('DRAFT');
            $table->text('catatan_wali')->nullable();
            $table->integer('id_wali_kelas')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['nomor_induk', 'tahun_ajaran', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_raport');
    }
};