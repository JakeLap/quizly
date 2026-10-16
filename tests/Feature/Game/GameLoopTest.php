<?php

use App\Actions\Game\SubmitAnswer;
use App\Actions\Rooms\StartGame;
use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Events\RoomUpdated;
use App\Game\GameEngine;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Models\Round;
use App\Support\GuestCookies;
use App\Support\RoomState;
use App\Support\Token;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);
    $this->travelTo(Carbon::parse('2026-10-14 12:00:00.000'));
});

/**
 * A started game (in its intro) with the given players and enough questions.
 *
 * @return array{0: Game, 1: list<Player>}
 */
function startedGame(int $players = 2, int $rounds = 3, int $seconds = 10, int $questions = 5): array
{
    $game = Game::factory()->create(['code' => 'ABCD', 'settings' => ['rounds' => $rounds, 'seconds_per_round' => $seconds]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count($questions)->create();
    $game->categories()->attach($category);

    $list = [];
    foreach (range(1, $players) as $i) {
        $list[] = Player::factory()->for($game)->withToken("player-{$i}")->create();
    }

    app(StartGame::class)->handle($game);

    return [$game->fresh(), $list];
}

/** Advance the clock to the game's next phase change and let the game clock run. */
function tick(Game $game): Game
{
    test()->travelTo($game->fresh()->next_transition_at);
    Game::query()->update(['host_seen_at' => now()]); // the host screen is still there
    app(GameEngine::class)->advanceDue();

    return $game->fresh();
}

function currentRound(Game $game): Round
{
    return app(GameEngine::class)->currentRound($game->fresh());
}

function answer(Player $player, Round $round, int $slot, ?int $afterOpenMs = 0): void
{
    app(SubmitAnswer::class)->handle($player, $round->number, $slot, $round->opens_at->addMilliseconds($afterOpenMs));
}

function correctSlot(Round $round): int
{
    return $round->correctSlot();
}

test('starting shows the intro, then the first question', function () {
    [$game] = startedGame();

    expect($game->status)->toBe(GameStatus::Intro)
        ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:00:04.000');

    // Nothing happens before the intro is over.
    $this->travelTo(Carbon::parse('2026-10-14 12:00:03.900'));
    expect(app(GameEngine::class)->advanceDue())->toBe(0);

    $game = tick($game);
    $round = currentRound($game);

    expect($game->status)->toBe(GameStatus::Question)
        ->and($game->current_round)->toBe(1)
        ->and($round->status)->toBe(RoundStatus::Open)
        ->and($round->opens_at->format('H:i:s.v'))->toBe('12:00:07.000')
        ->and($round->ends_at->format('H:i:s.v'))->toBe('12:00:17.000')
        ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:00:17.300')
        ->and(collect($round->option_order)->sort()->values()->all())->toBe([0, 1, 2, 3]);
});

test('the room state never gives away the answer while the question is open', function () {
    [$game, [$player]] = startedGame();
    $game = tick($game);
    answer($player, currentRound($game), 0);

    $state = RoomState::of($game->fresh());

    expect($state['round']['reveal'])->toBeNull()
        ->and($state['round']['answered'])->toBe([$player->id])
        ->and(json_encode($state))->not->toContain('correct')
        ->and(json_encode($state))->not->toContain('points');
});

test('a correct answer scores by speed, but only counts at the reveal', function () {
    [$game, [$player]] = startedGame(seconds: 10);
    $game = tick($game);
    $round = currentRound($game);

    answer($player, $round, correctSlot($round), afterOpenMs: 2000);

    $answer = $round->answers()->sole();
    expect($answer->is_correct)->toBeTrue()
        ->and($answer->response_ms)->toBe(2000)
        ->and($answer->points)->toBe(900) // 1000 − 500 × 2/10
        ->and($player->fresh()->score)->toBe(0);

    $game = tick($game);

    expect($game->status)->toBe(GameStatus::Reveal)
        ->and($player->fresh()->score)->toBe(900)
        ->and($round->question->fresh()->times_asked)->toBe(1)
        ->and($round->question->fresh()->times_correct)->toBe(1);
});

test('when two players are right, the faster one earns more', function () {
    [$game, [$fast, $slow]] = startedGame();
    $game = tick($game);
    $round = currentRound($game);

    answer($slow, $round, correctSlot($round), afterOpenMs: 6000);
    answer($fast, $round, correctSlot($round), afterOpenMs: 1500);

    expect($fast->fresh()->score)->toBeGreaterThan($slow->fresh()->score)
        ->and($slow->fresh()->score)->toBeGreaterThanOrEqual(500);
});

test('a wrong answer earns nothing', function () {
    [$game, [$player, $other]] = startedGame();
    $game = tick($game);
    $round = currentRound($game);

    answer($player, $round, (correctSlot($round) + 1) % 4, afterOpenMs: 100);

    expect($round->answers()->sole()->points)->toBe(0);
});

test('the round ends early once everyone has answered', function () {
    [$game, [$first, $second]] = startedGame();
    $game = tick($game);
    $round = currentRound($game);

    answer($first, $round, 0, afterOpenMs: 1000);
    expect($game->fresh()->status)->toBe(GameStatus::Question);

    answer($second, $round, 1, afterOpenMs: 1200);

    $game->refresh();
    expect($game->status)->toBe(GameStatus::Reveal)
        ->and($round->fresh()->status)->toBe(RoundStatus::Revealed)
        ->and(RoomState::of($game)['round']['reveal']['correctSlot'])->toBe(correctSlot($round));
});

test('answers outside the answering window are rejected', function (int $afterOpenMs, string $message) {
    [$game, [$player]] = startedGame(seconds: 10);
    $game = tick($game);

    expect(fn () => answer($player, currentRound($game), 0, $afterOpenMs))
        ->toThrow(ValidationException::class, $message);
})->with([
    'too early' => [-300, 'Wait for the answers to unlock.'],
    'too late' => [10301, "Time's up!"],
]);

test('slightly early or late answers are accepted', function (int $afterOpenMs, int $expectedMs) {
    [$game, [$player]] = startedGame(seconds: 10);
    $game = tick($game);
    answer($player, $round = currentRound($game), 0, $afterOpenMs);

    expect($round->answers()->sole()->response_ms)->toBe($expectedMs);
})->with([
    'phone clock a little ahead' => [-200, 0],
    'in flight at the deadline' => [10250, 10000],
]);

test('a player answers each question only once', function () {
    [$game, [$player]] = startedGame();
    $game = tick($game);
    answer($player, currentRound($game), 0);

    expect(fn () => answer($player, currentRound($game), 1))
        ->toThrow(ValidationException::class, 'You have already answered.');
});

test('answers to an earlier question are rejected', function () {
    [$game, [$player]] = startedGame();
    $game = tick($game);
    $first = currentRound($game);
    $game = tick($game); // reveal
    $game = tick($game); // scoreboard
    $game = tick($game); // question 2

    expect(fn () => app(SubmitAnswer::class)->handle($player, $first->number, 0, now()))
        ->toThrow(ValidationException::class, 'This question is over.');
});

test('a whole game runs to the final standings without repeating questions', function () {
    [$game, [$winner, $runnerUp]] = startedGame(rounds: 2, questions: 2);

    $statuses = [];
    $game = tick($game);

    while ($game->status !== GameStatus::Finished && count($statuses) < 20) {
        $statuses[] = $game->status->value;

        if ($game->status === GameStatus::Question) {
            $round = currentRound($game);
            answer($winner, $round, correctSlot($round), afterOpenMs: 500);
            answer($runnerUp, $round, (correctSlot($round) + 1) % 4, afterOpenMs: 400);
            $game->refresh();

            continue;
        }

        $game = tick($game);
    }

    $standings = RoomState::of($game)['standings'];

    expect($statuses)->toBe(['question', 'reveal', 'scoreboard', 'question', 'reveal'])
        ->and($game->rounds()->pluck('question_id')->unique())->toHaveCount(2)
        ->and($game->next_transition_at)->toBeNull()
        ->and($game->finished_at)->not->toBeNull()
        ->and($standings[0])->toMatchArray(['playerId' => $winner->id, 'rank' => 1, 'correct' => 2])
        ->and($standings[1])->toMatchArray(['playerId' => $runnerUp->id, 'rank' => 2, 'score' => 0]);
});

test('the game ends early if it runs out of questions', function () {
    [$game] = startedGame(rounds: 3, questions: 3);
    Question::query()->update(['status' => 'rejected']);

    $game = tick($game);

    expect($game->status)->toBe(GameStatus::Finished);
});

test('the host can skip ahead', function () {
    [$game] = startedGame();
    $game->forceFill(['host_token_hash' => Token::hash('host-token')])->save();

    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.next', $game))->assertRedirect();
    expect($game->fresh()->status)->toBe(GameStatus::Question);

    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.next', $game))->assertRedirect();
    expect($game->fresh()->status)->toBe(GameStatus::Reveal);
});

test('players answer from their phones', function () {
    [$game, [$player]] = startedGame();
    $game = tick($game);
    $round = currentRound($game);
    $this->travelTo($round->opens_at->addMilliseconds(1234));

    $this->withCookie(GuestCookies::PLAYER, 'player-1')
        ->post(route('play.answer', $game), ['round' => 1, 'slot' => 2])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $answer = $round->answers()->sole();
    expect($answer->player_id)->toBe($player->id)
        ->and($answer->option_index)->toBe($round->option_order[2])
        ->and($answer->response_ms)->toBe(1234);

    $this->withCookie(GuestCookies::PLAYER, 'player-1')
        ->get('/play/ABCD')
        ->assertInertia(fn ($page) => $page->where('myAnswer', ['match' => 1, 'round' => 1, 'slot' => 2]));
});

test('the final results stay viewable after the game', function () {
    [$game] = startedGame(rounds: 1);
    $game->forceFill(['status' => GameStatus::Finished, 'finished_at' => now()])->save();

    $this->withCookie(GuestCookies::PLAYER, 'player-1')
        ->get('/play/ABCD')
        ->assertInertia(fn ($page) => $page->component('play/Room')->where('room.status', 'finished'));
});

test('the game clock command advances due games', function () {
    [$game] = startedGame();
    $this->travelTo($game->next_transition_at);

    $this->artisan('quizly:clock', ['--once' => true])->assertSuccessful();

    expect($game->fresh()->status)->toBe(GameStatus::Question);
});
