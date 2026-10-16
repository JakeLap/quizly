<?php

namespace App\Console\Commands;

use App\Content\QuestionFiles;
use App\Content\Wikidata\QuestionBuilder;
use App\Content\Wikidata\QuestionTemplate;
use App\Content\Wikidata\Templates;
use App\Content\Wikidata\WikidataClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('quizly:generate-wikidata-questions
    {--category=* : Only these categories (slugs)}
    {--template=* : Only these templates (ids)}
    {--dry-run : Show counts and examples without writing files}')]
#[Description('Generate questions from Wikidata into database/data/questions/wikidata/{category}.json')]
class GenerateWikidataQuestionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WikidataClient $wikidata, QuestionBuilder $builder, QuestionFiles $files): int
    {
        $templates = array_values(array_filter(Templates::all(), fn (QuestionTemplate $template) => ($this->option('category') === [] || in_array($template->category, (array) $this->option('category'), true))
            && ($this->option('template') === [] || in_array($template->id, (array) $this->option('template'), true))));

        if ($templates === []) {
            $this->components->error('No templates match.');

            return self::FAILURE;
        }

        /** @var array<string, array<string, list<array<string, mixed>>>> $byCategory */
        $byCategory = [];
        $failed = false;

        foreach ($templates as $template) {
            try {
                $excluded = (array) config('quizly.wikidata.exclude');
                $questions = array_values(array_filter(
                    $builder->build($template, $wikidata->select($template->sparql)),
                    fn (array $question) => ! in_array($question['ref'], $excluded, true),
                ));
            } catch (Throwable $e) {
                $this->components->error("{$template->id}: {$e->getMessage()}");
                $failed = true;

                continue;
            }

            $this->components->twoColumnDetail("{$template->category} / {$template->id}", count($questions).' questions');

            // Zero questions almost always means the query broke, not that the
            // facts disappeared: keep the template's existing questions.
            if ($questions === []) {
                $this->components->warn("{$template->id} produced no questions; its existing questions are kept.");
                $failed = true;

                continue;
            }

            if ($this->option('dry-run')) {
                foreach (array_slice($questions, 0, 2) as $question) {
                    $el = $question['translations']['el'];
                    $this->line('    '.$el['text'].' → '.$el['options'][$question['correct']].'  ['.implode(' | ', $el['options']).']');
                }
            }

            $byCategory[$template->category][$template->id] = $questions;
        }

        if (! $this->option('dry-run')) {
            foreach ($byCategory as $category => $byTemplate) {
                $this->write($files, $category, $byTemplate);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Replace the questions of the templates that ran, keeping the rest of
     * the category's file.
     *
     * @param  array<string, list<array<string, mixed>>>  $byTemplate
     */
    private function write(QuestionFiles $files, string $category, array $byTemplate): void
    {
        $path = "wikidata/{$category}.json";

        $kept = array_filter($files->read($path), fn (array $question) => ! array_key_exists(strstr((string) $question['ref'], ':', true) ?: '', $byTemplate));
        $questions = array_merge(array_values($kept), ...array_values($byTemplate));
        $files->write($path, $questions);

        $this->components->info('Wrote '.count($questions)." questions to {$path}.");
    }
}
