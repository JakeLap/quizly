<?php

use App\Actions\Questions\ImportQuestions;
use App\Enums\Difficulty;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Models\Category;
use App\Models\Question;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Category::factory()->create(['slug' => 'geography']);
});

function questionItem(array $overrides = []): array
{
    return array_replace_recursive([
        'ref' => 'geo-1',
        'category' => 'geography',
        'difficulty' => 'easy',
        'correct' => 1,
        'translations' => [
            'el' => ['text' => 'Ποιο είναι το μεγαλύτερο νησί της Ελλάδας;', 'options' => ['Εύβοια', 'Κρήτη', 'Λέσβος', 'Ρόδος']],
            'en' => ['text' => 'What is the largest island in Greece?', 'options' => ['Euboea', 'Crete', 'Lesbos', 'Rhodes']],
        ],
    ], $overrides);
}

test('valid questions are imported with their translations as drafts', function () {
    $result = app(ImportQuestions::class)->handle([questionItem()]);

    $question = Question::with('translations')->sole();

    expect($result->created)->toBe(1)
        ->and($result->hasFailures())->toBeFalse()
        ->and($question->status)->toBe(QuestionStatus::Draft)
        ->and($question->difficulty)->toBe(Difficulty::Easy)
        ->and($question->source)->toBe(QuestionSource::Manual)
        ->and($question->correct_index)->toBe(1)
        ->and($question->translation('el')->options[1])->toBe('Κρήτη')
        ->and($question->translation('en')->text)->toBe('What is the largest island in Greece?');
});

test('importing the same ref again updates instead of duplicating', function () {
    $import = app(ImportQuestions::class);
    $import->handle([questionItem()], QuestionStatus::Published);
    Question::query()->update(['status' => QuestionStatus::Rejected]);

    $result = $import->handle([questionItem(['difficulty' => 'hard'])], QuestionStatus::Published);

    $question = Question::sole();
    expect($result->updated)->toBe(1)
        ->and($result->created)->toBe(0)
        ->and($question->difficulty)->toBe(Difficulty::Hard)
        ->and($question->status)->toBe(QuestionStatus::Rejected);
});

test('invalid questions are reported and skipped', function (array $overrides, string $error) {
    $item = questionItem($overrides);
    if (array_key_exists('_unset', $overrides)) {
        unset($item['translations'][$overrides['_unset']]['options'][3]);
    }

    $result = app(ImportQuestions::class)->handle([$item]);

    expect(Question::count())->toBe(0)
        ->and($result->failed)->toHaveCount(1)
        ->and($result->failed[0]['ref'])->toBe('geo-1')
        ->and(implode(' ', $result->failed[0]['errors']))->toContain($error);
})->with([
    'unknown category' => [['category' => 'astrology'], 'category'],
    'correct index out of range' => [['correct' => 4], 'correct'],
    'unsupported locale' => [['translations' => ['de' => ['text' => 'Was?', 'options' => ['a', 'b', 'c', 'd']]]], 'Unsupported locale(s): de'],
    'three options' => [['_unset' => 'en'], 'options'],
    'duplicate options' => [['translations' => ['el' => ['options' => ['Κρήτη', 'κρήτη', 'Λέσβος', 'Ρόδος']]]], 'The el options must all be different'],
]);

test('options may repeat across languages', function () {
    $result = app(ImportQuestions::class)->handle([questionItem([
        'translations' => [
            'el' => ['options' => ['Ag', 'Au', 'Gd', 'Go']],
            'en' => ['options' => ['Ag', 'Au', 'Gd', 'Go']],
        ],
    ])]);

    expect($result->created)->toBe(1);
});

test('the import command reads a JSON file', function () {
    $path = tempnam(sys_get_temp_dir(), 'questions');
    file_put_contents($path, json_encode([questionItem(), questionItem(['ref' => 'geo-2', 'category' => 'nope'])]));

    $this->artisan('quizly:import-questions', ['path' => $path, '--publish' => true])
        ->expectsOutputToContain('1 created, 0 updated, 1 failed.')
        ->assertFailed();

    expect(Question::sole()->status)->toBe(QuestionStatus::Published);

    unlink($path);
});

test('the import command rejects a missing file', function () {
    $this->artisan('quizly:import-questions', ['path' => '/nope.json'])->assertFailed();
});

test('the starter content is valid and playable in both languages', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Category::count())->toBe(11) // the seeder reuses the 'geography' slug from beforeEach
        ->and(Question::query()->playableIn('el')->count())->toBe(45)
        ->and(Question::query()->playableIn('en')->count())->toBe(45);
});
