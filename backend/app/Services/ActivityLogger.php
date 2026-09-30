<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Library;
use App\Models\Node;
use App\Models\User;

class ActivityLogger
{
    public function log(string $action, ?User $user, ?Library $library = null, ?Node $node = null, ?string $path = null, array $details = []): Activity
    {
        return Activity::create([
            'user_id' => $user?->id,
            'library_id' => $library?->id ?? $node?->library_id,
            'node_id' => $node?->id,
            'action' => $action,
            'path' => $path ?? $node?->path(),
            'details' => $details ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
