<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates or updates a login from the server's command line, for faculty and admins.
 * Prints a new random password once. Nothing is stored in plain text.
 */
class HaldenUser extends Command
{
    protected $signature = 'halden:user {email} {name} {--role=faculty : admin, faculty or student} {--reset-password : give an existing user a new password}';

    protected $description = 'Create or update a Halden login and print its password once';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->option('role');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That email address doesn\'t look right.');

            return self::FAILURE;
        }
        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_FACULTY, User::ROLE_STUDENT], true)) {
            $this->error('Role must be admin, faculty or student.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        $password = null;
        if ($user === null || $this->option('reset-password')) {
            $password = Str::password(20, symbols: false);
        }

        $user ??= new User(['email' => $email]);
        $user->forceFill(array_filter([
            'name' => (string) $this->argument('name'),
            'role' => $role,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'password' => $password === null ? null : Hash::make($password),
        ], fn ($v) => $v !== null))->save();

        $this->info("$email is set up as $role.");
        if ($password !== null) {
            $this->line("Password (shown once, copy it now): $password");
        }

        return self::SUCCESS;
    }
}
