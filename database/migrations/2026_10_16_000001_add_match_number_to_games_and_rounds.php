<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A room can host several games in a row ("play again"); each one is a
     * match, numbered within the room. Earlier matches' rounds are kept.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('match_number')->default(1)->after('current_round');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedSmallInteger('match_number')->default(1)->after('game_id');
            $table->dropUnique(['game_id', 'number']);
            $table->unique(['game_id', 'match_number', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'match_number', 'number']);
            $table->unique(['game_id', 'number']);
            $table->dropColumn('match_number');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('match_number');
        });
    }
};
