<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Creates a single-row settings table for the MC Logs addon.
     * `max_entries` controls how many upload records are kept per server
     * before the oldest ones are automatically pruned.
     */
    public function up(): void
    {
        Schema::create('mclogs_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('max_entries')->default(50);
            $table->timestamps();
        });

        // Seed the one and only settings row.
        DB::table('mclogs_settings')->insert([
            'max_entries' => 50,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mclogs_settings');
    }
};
