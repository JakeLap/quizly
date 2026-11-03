<?php

namespace App\Http\Requests\Rooms;

use App\Data\GameSettings;
use App\Support\QuizlyConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rounds' => ['required', 'integer', 'between:'.config('quizly.rounds.min').','.config('quizly.rounds.max')],
            'seconds_per_round' => ['required', 'integer', 'between:'.config('quizly.seconds_per_round.min').','.config('quizly.seconds_per_round.max')],
            'difficulties' => ['required', 'array', 'min:1'],
            'difficulties.*' => ['string', 'distinct', Rule::in(GameSettings::ALL_DIFFICULTIES)],
            'max_players' => ['required', 'integer', 'between:'.config('quizly.players.min').','.config('quizly.players.max')],
            'locale' => ['required', 'string', Rule::in(QuizlyConfig::locales())],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'difficulties.required' => __('Pick at least one difficulty.'),
            'difficulties.min' => __('Pick at least one difficulty.'),
            'categories.required' => __('Pick at least one category.'),
            'categories.min' => __('Pick at least one category.'),
        ];
    }

    public function settings(): GameSettings
    {
        return GameSettings::fromArray($this->validated());
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_values(array_map(intval(...), $this->validated('categories')));
    }
}
