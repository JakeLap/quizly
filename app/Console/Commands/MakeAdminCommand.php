<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('quizly:make-admin {email : The admin\'s email address} {--name= : Their name, for a new account}')]
#[Description('Create an admin who can manage the questions, or make an existing user one')]
class MakeAdminCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        if (! is_string($email) || Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error('That isn\'t a valid email address.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim($email));

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            $user->forceFill(['is_admin' => true])->save();
            $this->components->info("{$email} is now an admin.");

            return self::SUCCESS;
        }

        // Sign-ups are off, so this is how admins get an account.
        $name = $this->option('name');
        $name = is_string($name) && trim($name) !== '' ? trim($name) : text('Name', required: true);
        $secret = password(
            'Password',
            required: true,
            validate: fn (string $value) => Validator::make(['password' => $value], ['password' => Password::defaults()])->errors()->first('password') ?: null,
        );

        User::query()->forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => $secret,
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $this->components->info("Created the admin {$email}. Log in at ".route('login').'.');

        return self::SUCCESS;
    }
}
