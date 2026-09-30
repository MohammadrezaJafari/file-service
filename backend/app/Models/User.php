<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'is_admin', 'is_active', 'quota_bytes', 'used_bytes', 'avatar_path', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_admin' => false,
        'is_active' => true,
        'used_bytes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'quota_bytes' => 'integer',
            'used_bytes' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && $this->is_active;
    }

    public function libraries(): HasMany
    {
        return $this->hasMany(Library::class, 'owner_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')->withPivot('role')->withTimestamps();
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function stars(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'stars')->withTimestamps();
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function effectiveQuota(): ?int
    {
        if ($this->quota_bytes !== null) {
            return $this->quota_bytes;
        }

        $default = (int) Setting::get('default_quota_bytes', 0);

        return $default > 0 ? $default : null;
    }

    public function hasSpaceFor(int $bytes): bool
    {
        $quota = $this->effectiveQuota();

        return $quota === null || ($this->used_bytes + $bytes) <= $quota;
    }
}
