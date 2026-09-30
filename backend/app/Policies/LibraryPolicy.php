<?php

namespace App\Policies;

use App\Models\Library;
use App\Models\User;
use App\Support\Permission;

class LibraryPolicy
{
    public function view(User $user, Library $library): bool
    {
        return Permission::canRead(Permission::forLibrary($user, $library));
    }

    public function write(User $user, Library $library): bool
    {
        return Permission::canWrite(Permission::forLibrary($user, $library));
    }

    public function manage(User $user, Library $library): bool
    {
        return $user->is_admin || $library->owner_id === $user->id;
    }

    public function update(User $user, Library $library): bool
    {
        return $this->manage($user, $library);
    }

    public function delete(User $user, Library $library): bool
    {
        return $this->manage($user, $library);
    }
}
