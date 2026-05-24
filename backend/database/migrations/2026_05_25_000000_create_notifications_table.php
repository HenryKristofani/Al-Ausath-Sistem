<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Disable wrapping this migration in a transaction to allow IF NOT EXISTS statements.
     */
    public $withinTransaction = false;
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });

            // create index for morphs safely (Postgres supports IF NOT EXISTS)
            DB::statement('CREATE INDEX IF NOT EXISTS notifications_notifiable_type_notifiable_id_index ON notifications (notifiable_type, notifiable_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
