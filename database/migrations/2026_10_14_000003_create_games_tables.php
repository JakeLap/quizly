<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->char('code', 4);
            $table->char('host_token_hash', 64)->unique();
            $table->string('locale', 5);
            $table->string('status', 20)->default('lobby');
            $table->jsonb('settings');
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedInteger('state_version')->default(0);
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index('code');
        });

        // A room code only has to be unique while the room is still in use, so
        // codes of finished or abandoned rooms can be handed out again.
        DB::statement(
            "CREATE UNIQUE INDEX games_code_active_unique ON games (code) WHERE status NOT IN ('finished', 'abandoned')"
        );

        Schema::create('game_category', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->primary(['game_id', 'category_id']);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('nickname', 20);
            $table->string('color', 7);
            $table->string('avatar')->nullable();
            $table->integer('score')->default(0);
            $table->boolean('is_connected')->default(true);
            $table->boolean('is_vip')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('kicked_at')->nullable();
            $table->timestamps();
        });

        // Nicknames are unique per room regardless of letter case ("Nikos" and "nikos").
        DB::statement(
            'CREATE UNIQUE INDEX players_game_id_nickname_unique ON players (game_id, lower(nickname))'
        );

        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->jsonb('option_order');
            $table->string('status', 20)->default('pending');
            $table->timestamp('shown_at', 3)->nullable();
            $table->timestamp('opens_at', 3)->nullable();
            $table->timestamp('ends_at', 3)->nullable();
            $table->timestamp('closed_at', 3)->nullable();
            $table->timestamps(3);

            $table->unique(['game_id', 'number']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option_index');
            $table->boolean('is_correct');
            $table->unsignedInteger('response_ms');
            $table->unsignedInteger('points')->default(0);
            $table->timestamp('received_at', 3);
            $table->timestamps(3);

            $table->unique(['round_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
        Schema::dropIfExists('rounds');
        Schema::dropIfExists('players');
        Schema::dropIfExists('game_category');
        Schema::dropIfExists('games');
    }
};
