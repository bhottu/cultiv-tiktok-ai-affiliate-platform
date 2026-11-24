<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;

class TikTokAccountPolicy
{
    public function view(User $user, SocialAccount $account): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $account->user_id === null
            ? in_array($user->role, ['owner', 'admin'], true)
            : $account->user_id === $user->id;
    }

    public function manage(User $user, SocialAccount $account): bool
    {
        return $this->view($user, $account);
    }
}
