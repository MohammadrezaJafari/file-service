<?php

namespace App\Support;

use App\Models\Library;
use App\Models\Node;
use App\Models\Share;
use App\Models\User;

/**
 * Resolves the effective permission a user has on a library or a node.
 * Returns 'rw', 'r' or null (no access).
 */
class Permission
{
    public const READ = 'r';

    public const READ_WRITE = 'rw';

    public static function forLibrary(?User $user, Library $library): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->is_admin || $library->owner_id === $user->id) {
            return self::READ_WRITE;
        }

        $shares = static::sharesFor($user, $library)->whereNull('node_id')->get();

        return static::best($shares);
    }

    public static function forNode(?User $user, Node $node): ?string
    {
        if (! $user) {
            return null;
        }

        $library = $node->library;

        if ($user->is_admin || $library->owner_id === $user->id) {
            return self::READ_WRITE;
        }

        $shares = static::sharesFor($user, $library)->get();
        if ($shares->isEmpty()) {
            return null;
        }

        $libraryLevel = static::best($shares->whereNull('node_id'));

        $ancestorIds = array_map(fn (Node $n) => $n->id, $node->ancestors());
        $ancestorIds[] = $node->id;

        $folderLevel = static::best($shares->filter(fn (Share $s) => $s->node_id && in_array($s->node_id, $ancestorIds, true)));

        return static::max($libraryLevel, $folderLevel);
    }

    public static function canRead(?string $permission): bool
    {
        return in_array($permission, [self::READ, self::READ_WRITE], true);
    }

    public static function canWrite(?string $permission): bool
    {
        return $permission === self::READ_WRITE;
    }

    protected static function sharesFor(User $user, Library $library)
    {
        $groupIds = $user->groups()->pluck('groups.id')->all();

        return Share::query()
            ->where('library_id', $library->id)
            ->where(function ($q) use ($user, $groupIds) {
                $q->where('user_id', $user->id);
                if ($groupIds) {
                    $q->orWhereIn('group_id', $groupIds);
                }
            });
    }

    protected static function best($shares): ?string
    {
        $best = null;
        foreach ($shares as $share) {
            $best = static::max($best, $share->permission);
        }

        return $best;
    }

    protected static function max(?string $a, ?string $b): ?string
    {
        if ($a === self::READ_WRITE || $b === self::READ_WRITE) {
            return self::READ_WRITE;
        }
        if ($a === self::READ || $b === self::READ) {
            return self::READ;
        }

        return null;
    }
}
