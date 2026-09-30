<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Library extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'description', 'is_encrypted', 'password_hash', 'size_bytes', 'file_count',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'size_bytes' => 'integer',
            'file_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function rootNodes(): HasMany
    {
        return $this->hasMany(Node::class)->whereNull('parent_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
