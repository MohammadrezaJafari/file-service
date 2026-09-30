<?php

namespace App\Policies;

use App\Models\Node;
use App\Models\User;
use App\Support\Permission;

class NodePolicy
{
    public function view(User $user, Node $node): bool
    {
        return Permission::canRead(Permission::forNode($user, $node));
    }

    public function write(User $user, Node $node): bool
    {
        return Permission::canWrite(Permission::forNode($user, $node));
    }

    public function share(User $user, Node $node): bool
    {
        return $user->is_admin || $node->library->owner_id === $user->id || $this->write($user, $node);
    }
}
