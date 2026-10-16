<?php

namespace App\Content;

use Illuminate\Support\Facades\File;

/**
 * The question JSON files under database/data/questions, which the question
 * seeder imports: starter.json and wikidata/*.json.
 */
class QuestionFiles
{
    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $path): array
    {
        $absolute = $this->root().'/'.$path;

        return is_file($absolute) ? array_values(json_decode((string) file_get_contents($absolute), true, flags: JSON_THROW_ON_ERROR)) : [];
    }

    /**
     * Write a file's questions, sorted by ref so diffs stay readable.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    public function write(string $path, array $questions): void
    {
        usort($questions, fn (array $a, array $b) => strnatcmp((string) ($a['ref'] ?? ''), (string) ($b['ref'] ?? '')));

        $absolute = $this->root().'/'.$path;
        File::ensureDirectoryExists(dirname($absolute));
        file_put_contents($absolute, json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    private function root(): string
    {
        return rtrim((string) config('quizly.question_files'), '/');
    }
}
