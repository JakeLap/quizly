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
