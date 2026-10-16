<?php

use App\Console\Commands\SeedContentCommand;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->folder = storage_path('framework/testing/questions-'.uniqid());
    File::ensureDirectoryExists($this->folder);
    config(['quizly.question_files' => $this->folder]);

    $this->writeQuestions = function (string $englishText) {
        File::put("{$this->folder}/starter.json", json_encode([[
            'ref' => 'geo-1',
            'category' => 'geography',
            'difficulty' => 'easy',
            'correct' => 1,
            'translations' => [
                'el' => ['text' => 'Ποιο είναι το μεγαλύτερο νησί της Ελλάδας;', 'options' => ['Εύβοια', 'Κρήτη', 'Λέσβος', 'Ρόδος']],
                'en' => ['text' => $englishText, 'options' => ['Euboea', 'Crete', 'Lesbos', 'Rhodes']],
            ],
        ]]));
    };
});

afterEach(function () {
    File::deleteDirectory($this->folder);
});

test('deploys seed the categories and questions once, and again when they change', function () {
    ($this->writeQuestions)('What is the largest island in Greece?');

    $this->artisan('quizly:seed-content')->assertSuccessful();

    expect(Category::query()->where('slug', 'geography')->exists())->toBeTrue()
        ->and(Question::query()->count())->toBe(1)
        ->and(Cache::get(SeedContentCommand::CACHE_KEY))->not->toBeNull();

    // Nothing changed: nothing is imported.
    Question::query()->delete();
    $this->artisan('quizly:seed-content')->expectsOutputToContain('up to date')->assertSuccessful();
    expect(Question::query()->count())->toBe(0);

    // New content ships: it is imported.
    ($this->writeQuestions)('Which is the largest Greek island?');
    $this->artisan('quizly:seed-content')->assertSuccessful();
    expect(Question::query()->count())->toBe(1);
});

test('seeding can be forced', function () {
    ($this->writeQuestions)('What is the largest island in Greece?');
    $this->artisan('quizly:seed-content')->assertSuccessful();
    Question::query()->delete();

    $this->artisan('quizly:seed-content', ['--force' => true])->assertSuccessful();

    expect(Question::query()->count())->toBe(1);
});
