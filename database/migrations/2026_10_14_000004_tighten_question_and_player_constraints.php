<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Questions always have exactly four options (quizly.options_per_question).
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_correct_index_check CHECK (correct_index BETWEEN 0 AND 3)');

        // A kicked player's nickname becomes available again.
        DB::statement('DROP INDEX players_game_id_nickname_unique');
        DB::statement('CREATE UNIQUE INDEX players_game_id_nickname_unique ON players (game_id, lower(nickname)) WHERE kicked_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX players_game_id_nickname_unique');
        DB::statement('CREATE UNIQUE INDEX players_game_id_nickname_unique ON players (game_id, lower(nickname))');

        DB::statement('ALTER TABLE questions DROP CONSTRAINT questions_correct_index_check');
    }
};
