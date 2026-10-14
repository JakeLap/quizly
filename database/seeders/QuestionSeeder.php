<?php

namespace Database\Seeders;

use App\Actions\Questions\ImportQuestions;
use App\Enums\QuestionStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

class QuestionSeeder extends Seeder
{
    /**
     * Seed the hand-written starter questions from database/data/questions.json.
     */
    public function run(ImportQuestions $import): void
    {
        $items = json_decode(
            (string) file_get_contents(database_path('data/questions.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $result = $import->handle($items, QuestionStatus::Published);

        if ($result->hasFailures()) {
            throw new RuntimeException('Invalid starter questions: '.json_encode($result->failed, JSON_UNESCAPED_UNICODE));
        }

        $this->command->info("Questions: {$result->created} created, {$result->updated} updated.");
    }
}
