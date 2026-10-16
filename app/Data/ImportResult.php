<?php

namespace App\Data;

final class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    /**
     * @var list<array{index: int, ref: string|null, errors: list<string>}>
     */
    public array $failed = [];

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
