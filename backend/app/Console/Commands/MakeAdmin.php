<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** EF-1205: grant the admin role to an existing account (spec §8, admin seeding). */
#[Signature('user:make-admin {email : Email address of the account to promote}')]
#[Description('Grant the administrator role to a user.')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info("{$user->email} is now an administrator.");

        return self::SUCCESS;
    }
}
