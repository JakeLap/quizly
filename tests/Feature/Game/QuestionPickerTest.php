<?php

use App\Actions\Questions\ImportQuestions;
use App\Data\GameSettings;
use App\Game\QuestionPicker;
use App\Models\Category;
use App\Models\Game;
use App\Models\Question;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * Play the picker for a number of rounds, as the game would.
 *
 * @return Collection<int, Question>
 */
function pickRounds(Game $game, int $rounds): Collection
{
    $picked = collect();

    for ($number = 1; $number <= $rounds; $number++) {
        $question = (new QuestionPicker)->pick($game->fresh());
        Round::factory()->for($game)->create([
            'match_number' => $game->match_number,
            'number' => $number,
            'question_id' => $question->id,
            'category_id' => $question->category_id,
        ]);
        $picked->push($question);
    }

    return $picked;
}

function roomWith(Category ...$categories): Game
{
    $game = Game::factory()->create(['locale' => 'en']);
    $game->categories()->attach(collect($categories)->pluck('id'));

    return $game;
}

test('a kind with many questions does not crowd out the others', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(20)->create(['kind' => 'country-calling-code']);
    Question::factory()->for($category)->create(['kind' => 'country-continent']);
    Question::factory()->for($category)->create(['kind' => 'country-highest-point']);

    $kinds = pickRounds(roomWith($category), 3)->pluck('kind');

    expect($kinds->unique())->toHaveCount(3);
});

test('a kind comes back only after the others had a turn', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(10)->create(['kind' => 'film-director']);
    Question::factory()->for($category)->count(10)->create(['kind' => 'film-year']);

    $kinds = pickRounds(roomWith($category), 6)->pluck('kind');

    expect($kinds->countBy()->all())->toEqual(['film-director' => 3, 'film-year' => 3])
        ->and($kinds->sliding(2)->every(fn (Collection $pair) => $pair->first() !== $pair->last()))->toBeTrue();
});

test('similar kinds count as one, also across categories', function () {
    $science = Category::factory()->create();
    $general = Category::factory()->create();
    Question::factory()->for($science)->count(5)->create(['kind' => 'nobel-physics-year']);
    Question::factory()->for($science)->count(5)->create(['kind' => 'element-symbol']);
    Question::factory()->for($general)->count(5)->create(['kind' => 'nobel-peace-year']);
    Question::factory()->for($general)->count(5)->create(['kind' => 'country-capital']);

    // One round from each category: the Nobel years never come up twice,
    // although each category has its own.
    $families = pickRounds(roomWith($science, $general), 2)
        ->map(fn (Question $question) => config('quizly.question_families')[$question->kind] ?? $question->kind);

    expect($families->unique())->toHaveCount(2);
});

test('categories take turns, never twice in a row', function () {
    $one = Category::factory()->create();
    $two = Category::factory()->create();
    Question::factory()->for($one)->count(10)->create(['kind' => 'a']);
    Question::factory()->for($two)->count(10)->create(['kind' => 'b']);

    $categories = pickRounds(roomWith($one, $two), 6)->pluck('category_id');

    expect($categories->countBy()->all())->toEqual([$one->id => 3, $two->id => 3])
        ->and($categories->sliding(2)->every(fn (Collection $pair) => $pair->first() !== $pair->last()))->toBeTrue();
});

test('a category\'s hand-written questions take turns with its kinds, as one', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(5)->create(['kind' => null]);
    Question::factory()->for($category)->count(10)->create(['kind' => 'book-author']);

    $kinds = pickRounds(roomWith($category), 4)->pluck('kind');

    expect($kinds->filter()->count())->toBe(2)
        ->and($kinds->chunk(2)->every(fn (Collection $pair) => $pair->filter()->count() === 1))->toBeTrue();
});

test('hand-written questions given a type take turns as that type', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(5)->create(['kind' => 'flags']);
    Question::factory()->for($category)->count(5)->create(['kind' => null]);
    Question::factory()->for($category)->count(5)->create(['kind' => 'book-author']);

    $kinds = pickRounds(roomWith($category), 3)->map(fn (Question $question) => $question->kind ?? 'hand-written');

    expect($kinds->unique())->toHaveCount(3);
});

test('the next game in the room starts the turns afresh but still asks new questions', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(4)->create(['kind' => 'a']);
    Question::factory()->for($category)->count(4)->create(['kind' => 'b']);
    $game = roomWith($category);

    $first = pickRounds($game, 2);
    $game->forceFill(['match_number' => 2])->save();
    $second = pickRounds($game, 2);

    expect($second->pluck('kind')->unique())->toHaveCount(2)
        ->and($second->pluck('id')->intersect($first->pluck('id')))->toBeEmpty();
});

test('only the room\'s difficulties are asked', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(5)->create(['difficulty' => 'easy']);
    Question::factory()->for($category)->count(5)->create(['difficulty' => 'hard']);
    $game = roomWith($category);
    $game->settings = new GameSettings(10, 20, 8, ['hard']);
    $game->save();

    expect(pickRounds($game, 5)->pluck('difficulty.value')->unique()->all())->toBe(['hard']);
});

test('imports give generated questions their template as kind', function () {
    Category::factory()->create(['slug' => 'general']);
    $item = fn (string $ref, string $source, array $extra = []) => [
        'ref' => $ref,
        'source' => $source,
        'category' => 'general',
        'correct' => 0,
        'translations' => ['en' => ['text' => "Question {$ref}?", 'options' => ['A', 'B', 'C', 'D']]],
        ...$extra,
    ];

    app(ImportQuestions::class)->handle([
        $item('country-calling-code:Q142', 'wikidata'),
        $item('seed-general-001', 'manual'),
        $item('seed-general-002', 'manual', ['kind' => 'flags']),
    ]);

    $kinds = Question::query()->pluck('kind', 'source_ref');

    expect($kinds['country-calling-code:Q142'])->toBe('country-calling-code')
        ->and($kinds['seed-general-001'])->toBeNull()
        ->and($kinds['seed-general-002'])->toBe('flags');
});
