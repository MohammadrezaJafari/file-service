<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $fillable = ['library_id', 'parent_id', 'name', 'color'];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_tag');
    }

    public function fullName(): string
    {
        $names = [$this->name];
        $p = $this->parent;
        $guard = 0;
        while ($p && $guard++ < 32) {
            array_unshift($names, $p->name);
            $p = $p->parent;
        }

        return implode(' / ', $names);
    }
}
