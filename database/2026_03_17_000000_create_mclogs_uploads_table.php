<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mclogs_uploads', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('mclogs_id', 100)->unique();
            $table->text('mclogs_url');
            $table->string('log_file_name', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            // Indices for common queries
            $table->index('server_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mclogs_uploads');
    }
};
