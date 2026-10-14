<?php

namespace App\Http\Requests\Rooms;

use App\Support\RoomCode;
use Illuminate\Foundation\Http\FormRequest;

class JoinRoomRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^['.RoomCode::ALPHABET.']{'.RoomCode::LENGTH.'}$/'],
            'nickname' => [
                'required',
                'string',
                'min:'.config('quizly.nickname.min'),
                'max:'.config('quizly.nickname.max'),
                // Letters (any script) and digits, plus spaces, dots, dashes and underscores inside.
                'regex:/^[\p{L}\p{N}](?:[\p{L}\p{N} ._-]*[\p{L}\p{N}])?$/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => __('Enter the room code shown on the screen.'),
            'code.regex' => __('Room codes are 4 letters.'),
            'nickname.required' => __('Pick a nickname.'),
            'nickname.min' => __('Your nickname needs at least :min characters.'),
            'nickname.max' => __('Your nickname can have at most :max characters.'),
            'nickname.regex' => __('Use only letters, numbers, spaces, dots and dashes.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => RoomCode::normalize($this->string('code')->toString()) ?? $this->string('code')->upper()->toString(),
            'nickname' => $this->string('nickname')->squish()->toString(),
        ]);
    }
}
