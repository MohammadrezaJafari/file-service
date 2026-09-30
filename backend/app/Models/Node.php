<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Node extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_FOLDER = 'folder';

    public const TYPE_FILE = 'file';

    protected $fillable = [
        'library_id', 'parent_id', 'type', 'name', 'size', 'mime_type', 'storage_path', 'hash',
        'is_encrypted', 'version_number', 'metadata', 'created_by', 'updated_by', 'deleted_by', 'deleted_from_path',
    ];

    protected $hidden = ['storage_path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version_number' => 'integer',
            'is_encrypted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Node::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class)->orderByDesc('version_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function starredBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'stars')->withTimestamps();
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'node_tag');
    }

    public function isFolder(): bool
    {
        return $this->type === self::TYPE_FOLDER;
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function scopeFolders(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_FOLDER);
    }

    public function scopeFiles(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_FILE);
    }

    /**
     * Ordered list of ancestors from the library root down to (excluding) this node.
     *
     * @return array<int, Node>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent()->withTrashed()->first();
        $guard = 0;
        while ($current && $guard++ < 512) {
            array_unshift($ancestors, $current);
            $current = $current->parent()->withTrashed()->first();
        }

        return $ancestors;
    }

    public function path(): string
    {
        $names = array_map(fn (Node $n) => $n->name, $this->ancestors());
        $names[] = $this->name;

        return '/'.implode('/', $names);
    }

    public function isDescendantOf(Node $other): bool
    {
        foreach ($this->ancestors() as $ancestor) {
            if ($ancestor->id === $other->id) {
                return true;
            }
        }

        return false;
    }
}
