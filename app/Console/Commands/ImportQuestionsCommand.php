<?php

namespace App\Console\Commands;

use App\Actions\Questions\ImportQuestions;
use App\Enums\QuestionStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;

#[Signature('quizly:import-questions {path : Path to a JSON file of questions} {--publish : Publish new questions instead of saving them as drafts}')]
#[Description('Import questions from a JSON file (see App\Actions\Questions\ImportQuestions for the format)')]
class ImportQuestionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImportQuestions $import): int
    {
        $path = $this->argument('path');

        if (! is_string($path)) {
            $this->components->error('Please pass a single file path.');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $items = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->components->error("Invalid JSON: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $this->components->error('The file must contain a JSON array of questions.');

            return self::FAILURE;
        }

        $status = $this->option('publish') ? QuestionStatus::Published : QuestionStatus::Draft;
        $result = $import->handle($items, $status);

        $this->components->info("{$result->created} created, {$result->updated} updated, ".count($result->failed).' failed.');

        foreach ($result->failed as $failure) {
            $this->components->twoColumnDetail(
                '#'.$failure['index'].($failure['ref'] ? " ({$failure['ref']})" : ''),
                implode(' ', $failure['errors']),
            );
        }

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
