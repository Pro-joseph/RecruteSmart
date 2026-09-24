<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function publish(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function close(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function duplicate(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function regenerateLink(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }

    public function manageFormFields(User $user, Offer $offer): bool
    {
        return $offer->user_id === $user->id;
    }
}
