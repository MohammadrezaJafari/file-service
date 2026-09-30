<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    public const PERM_READ = 'r';

    public const PERM_READ_WRITE = 'rw';

    protected $fillable = ['library_id', 'node_id', 'shared_by', 'user_id', 'group_id', 'permission'];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
