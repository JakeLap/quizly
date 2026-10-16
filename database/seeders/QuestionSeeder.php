<?php

namespace Database\Seeders;

use App\Actions\Questions\ImportQuestions;
use App\Enums\QuestionStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class QuestionSeeder extends Seeder
{
    /**
     * Seed every question file in database/data/questions:
     *
     *     starter.json        hand-written starter questions
     *     wikidata/*.json     generated from Wikidata (quizly:generate-wikidata-questions)
     *
     * Questions without their own status are published. Seeding again
     * updates questions in place instead of duplicating them.
     */
    public function run(ImportQuestions $import): void
    {
        $files = collect(File::allFiles(config('quizly.question_files')))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'json')
            ->sortBy(fn (SplFileInfo $file) => $file->getRelativePathname())
            ->values();

        foreach ($files as $file) {
            $items = json_decode((string) file_get_contents($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);
            $result = $import->handle($items, QuestionStatus::Published);

            if ($result->hasFailures()) {
                throw new RuntimeException("Invalid questions in {$file->getRelativePathname()}: ".json_encode(array_slice($result->failed, 0, 5), JSON_UNESCAPED_UNICODE));
            }

            $this->command->info("{$file->getRelativePathname()}: {$result->created} created, {$result->updated} updated.");
        }
    }
}
