<?php

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only published questions translated into the room locale are playable', function () {
    $both = Question::factory()->create();
    $greekOnly = Question::factory()->translatedInto(['el'])->create();
    $englishOnly = Question::factory()->translatedInto(['en'])->create();
    Question::factory()->draft()->create();

    expect(Question::query()->playableIn('el')->pluck('id')->sort()->values()->all())
        ->toBe([$both->id, $greekOnly->id])
        ->and(Question::query()->playableIn('en')->pluck('id')->sort()->values()->all())
        ->toBe([$both->id, $englishOnly->id]);
});

test('a missing translation falls back to the fallback locale', function () {
    config(['app.fallback_locale' => 'en']);
    $question = Question::factory()->translatedInto(['en'])->create();

    expect($question->translation('el')?->locale)->toBe('en')
        ->and($question->translatedLocales())->toBe(['en']);
});

test('the correct rate is tracked once a question has been asked', function () {
    expect(Question::factory()->make()->correctRate())->toBeNull()
        ->and(Question::factory()->make(['times_asked' => 4, 'times_correct' => 3])->correctRate())->toBe(0.75);
});
