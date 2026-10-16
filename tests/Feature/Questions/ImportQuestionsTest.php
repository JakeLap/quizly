<?php

use App\Actions\Questions\ImportQuestions;
use App\Enums\Difficulty;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Models\Category;
use App\Models\Question;
use Database\Seeders\CategorySeeder;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

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

test('the seeder imports every question file', function () {
    $directory = sys_get_temp_dir().'/quizly-questions-'.uniqid();
    mkdir("{$directory}/wikidata", recursive: true);
    file_put_contents("{$directory}/starter.json", json_encode([questionItem()]));
    file_put_contents("{$directory}/wikidata/geography.json", json_encode([
        questionItem(['ref' => 'geo-2', 'source' => 'wikidata', 'status' => 'draft']),
    ]));
    config(['quizly.question_files' => $directory]);

    $this->seed(QuestionSeeder::class);
    $this->seed(QuestionSeeder::class); // seeding again changes nothing

    expect(Question::count())->toBe(2)
        ->and(Question::where('source_ref', 'geo-1')->sole()->status)->toBe(QuestionStatus::Published)
        ->and(Question::where('source_ref', 'geo-2')->sole()->status)->toBe(QuestionStatus::Draft);

    File::deleteDirectory($directory);
});

test('an item can set its own status', function () {
    app(ImportQuestions::class)->handle([questionItem(['status' => 'draft'])], QuestionStatus::Published);

    expect(Question::sole()->status)->toBe(QuestionStatus::Draft);
});

test('items cannot import themselves as rejected', function () {
    $result = app(ImportQuestions::class)->handle([questionItem(['status' => 'rejected'])]);

    expect($result->failed)->toHaveCount(1);
});

test('every committed question file is valid and in both languages', function () {
    $this->seed(CategorySeeder::class);
    $import = app(ImportQuestions::class);
    $refs = [];

    foreach (File::allFiles(database_path('data/questions')) as $file) {
        $items = json_decode((string) file_get_contents($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);

        expect($import->validate($items)->failed)->toBe([], $file->getRelativePathname());

        foreach ($items as $item) {
            expect(array_keys($item['translations']))->toEqualCanonicalizing(['el', 'en']);
            $refs[] = ($item['source'] ?? 'manual').':'.$item['ref'];
        }
    }

    expect($refs)->toHaveCount(count(array_unique($refs)));
});
