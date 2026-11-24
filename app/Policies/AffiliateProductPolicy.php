<?php

namespace App\Policies;

use App\Models\AffiliateProduct;
use App\Models\User;

class AffiliateProductPolicy
{
    public function view(User $user, AffiliateProduct $product): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $product->user_id === null
            ? in_array($user->role, ['owner', 'admin'], true)
            : $product->user_id === $user->id;
    }

    public function analyze(User $user, AffiliateProduct $product): bool
    {
        return $this->view($user, $product);
    }

    public function approve(User $user, AffiliateProduct $product): bool
    {
        return $this->view($user, $product);
    }
}
