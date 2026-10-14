<?php

use App\Models\Answer;
use App\Models\Player;
use App\Models\Question;
use App\Models\Round;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('round timestamps keep millisecond precision', function () {
    $opensAt = Carbon::parse('2026-10-14 12:00:00.123');

    $round = Round::factory()->open($opensAt, seconds: 20)->create();

    expect($round->fresh()->opens_at->format('H:i:s.v'))->toBe('12:00:00.123')
        ->and($round->fresh()->ends_at->format('H:i:s.v'))->toBe('12:00:20.123');
});

test('display slots map to question options', function () {
    $question = Question::factory()->create(['correct_index' => 2]);
    $round = Round::factory()->for($question)->create(['option_order' => [3, 2, 0, 1]]);

    expect($round->optionIndexForSlot(0))->toBe(3)
        ->and($round->optionIndexForSlot(1))->toBe(2)
        ->and($round->optionIndexForSlot(4))->toBeNull()
        ->and($round->correctSlot())->toBe(1);
});

test('the round inherits the category of its question', function () {
    $round = Round::factory()->create();

    expect($round->category_id)->toBe($round->question->category_id);
});

test('a player can only answer a round once', function () {
    $round = Round::factory()->open()->create();
    $player = Player::factory()->for($round->game)->create();

    Answer::factory()->for($round)->for($player)->create();
    Answer::factory()->for($round)->for($player)->create();
})->throws(UniqueConstraintViolationException::class);

test('answers keep the millisecond they were received', function () {
    $answer = Answer::factory()->create(['received_at' => Carbon::parse('2026-10-14 12:00:05.678')]);

    expect($answer->fresh()->received_at->format('H:i:s.v'))->toBe('12:00:05.678');
});
