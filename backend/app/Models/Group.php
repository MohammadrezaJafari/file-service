<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name', 'description'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')->withPivot('role')->withTimestamps();
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    public function roleOf(User $user): ?string
    {
        if ($user->id === $this->owner_id) {
            return 'owner';
        }

        $member = $this->members()->where('users.id', $user->id)->first();

        return $member?->pivot?->role;
    }

    public function isAdmin(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin'], true);
    }
}
