<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Database\Seeders\QuestionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

#[Signature('quizly:seed-content {--force : Seed even if nothing changed}')]
#[Description('Seed the categories and questions, if they changed since the last time (run on every deploy)')]
class SeedContentCommand extends Command
{
    public const string CACHE_KEY = 'quizly:content-hash';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Importing every question takes a while, so only do it when the
        // content that ships with the app is new.
        $hash = $this->contentHash();

        if (! $this->option('force') && Cache::get(self::CACHE_KEY) === $hash) {
            $this->components->info('Categories and questions are up to date.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => CategorySeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => QuestionSeeder::class, '--force' => true]);

        Cache::forever(self::CACHE_KEY, $hash);

        return self::SUCCESS;
    }

    /**
     * A fingerprint of the category seeder and every question file.
     */
    private function contentHash(): string
    {
        $context = hash_init('sha256');
        hash_update_file($context, database_path('seeders/CategorySeeder.php'));

        collect(File::allFiles(config('quizly.question_files')))
            ->sortBy(fn (SplFileInfo $file) => $file->getRelativePathname())
            ->each(function (SplFileInfo $file) use ($context) {
                hash_update($context, $file->getRelativePathname());
                hash_update_file($context, $file->getPathname());
            });

        return hash_final($context);
    }
}
