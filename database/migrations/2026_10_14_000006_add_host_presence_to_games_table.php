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
        Schema::table('games', function (Blueprint $table) {
            // The host screen's last heartbeat; the game pauses when it goes quiet.
            $table->timestamp('host_seen_at')->nullable()->index()->after('next_transition_at');
            $table->timestamp('paused_at', 3)->nullable()->after('host_seen_at');
            // How much of the current phase was left when the game paused.
            $table->unsignedInteger('paused_remaining_ms')->nullable()->after('paused_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['host_seen_at', 'paused_at', 'paused_remaining_ms']);
        });
    }
};
