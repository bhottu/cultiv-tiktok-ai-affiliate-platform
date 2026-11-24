<?php

namespace App\Policies;

use App\Models\User;

class ProviderSettingPolicy
{
    public function manage(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['owner', 'admin'], true);
    }
}
