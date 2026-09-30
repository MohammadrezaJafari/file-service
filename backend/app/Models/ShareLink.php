<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareLink extends Model
{
    public const KIND_DOWNLOAD = 'download';

    public const KIND_UPLOAD = 'upload';

    protected $fillable = [
        'token', 'library_id', 'node_id', 'created_by', 'kind', 'password_hash', 'expires_at', 'allow_download',
        'view_count', 'download_count',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'allow_download' => 'boolean',
            'view_count' => 'integer',
            'download_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ShareLink $link) {
            $link->token ??= Str::random(20);
        });
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasPassword(): bool
    {
        return $this->password_hash !== null;
    }

    public function checkPassword(?string $password): bool
    {
        if (! $this->hasPassword()) {
            return true;
        }

        return $password !== null && Hash::check($password, $this->password_hash);
    }

    public function isUploadLink(): bool
    {
        return $this->kind === self::KIND_UPLOAD;
    }
}
