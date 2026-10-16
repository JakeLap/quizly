<?php

namespace App\Http\Requests\Game;

use App\Http\Middleware\CaptureRequestTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'round' => ['required', 'integer', 'min:1'],
            'slot' => ['required', 'integer', 'between:0,'.(config('quizly.options_per_question') - 1)],
        ];
    }

    /**
     * When the answer reached the server.
     */
    public function receivedAt(): CarbonImmutable
    {
        $receivedAt = $this->attributes->get(CaptureRequestTime::ATTRIBUTE);

        return $receivedAt instanceof CarbonImmutable ? $receivedAt : now();
    }
}
