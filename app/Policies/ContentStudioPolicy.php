<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContentStudioPolicy
{
    public function view(User $user, Model $resource): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $ownerId = $resource->getAttribute('user_id');

        return $ownerId === null
            ? in_array($user->role, ['owner', 'admin'], true)
            : (int) $ownerId === $user->id;
    }

    public function update(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }

    public function delete(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }

    public function approve(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }
}
