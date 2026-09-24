<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $application->offer->user_id === $user->id;
    }

    public function update(User $user, Application $application): bool
    {
        return $application->offer->user_id === $user->id;
    }

    public function delete(User $user, Application $application): bool
    {
        return $application->offer->user_id === $user->id;
    }
}
