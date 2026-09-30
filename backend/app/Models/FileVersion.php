<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'node_id', 'version_number', 'storage_path', 'size', 'mime_type', 'hash', 'is_encrypted', 'created_by', 'comment', 'created_at',
    ];

    protected $hidden = ['storage_path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version_number' => 'integer',
            'is_encrypted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
