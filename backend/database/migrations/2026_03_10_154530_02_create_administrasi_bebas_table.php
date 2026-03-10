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
        Schema::create('administrasi_bebas', function (Blueprint $table) {
        $table->integer('id_admin_bebas', true)->primary();
            $table->integer('id_santri')->nullable();
            $table->text('deskripsi')->nullable();
            $table->decimal('total_tagihan', 15, 2)->nullable();
            $table->decimal('sisa', 15, 2)->nullable();
            $table->string('status', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('id_santri');
            $table->foreign('id_santri')->references('id_santri')->on('data_santri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrasi_bebas');
    }
};