<?php

namespace App\Casts;

use App\Data\GameSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<GameSettings, GameSettings|array<string, mixed>>
 */
class AsGameSettings implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GameSettings
    {
        if ($value === null) {
            return null;
        }

        return GameSettings::fromArray(json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = GameSettings::fromArray($value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
