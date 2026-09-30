<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'parent_id' => $this->parent_id,
            'type' => $this->type,
            'name' => $this->name,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'hash' => $this->hash,
            'version_number' => $this->version_number,
            'is_encrypted' => $this->is_encrypted,
            'metadata' => $this->metadata ?? (object) [],
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color, 'parent_id' => $t->parent_id])),
            'has_thumbnail' => $this->isFile() && in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true),
            'path' => $this->when(isset($this->resolved_path), fn () => $this->resolved_path),
            'is_starred' => $this->when(isset($this->is_starred), fn () => (bool) $this->is_starred),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updater' => new UserResource($this->whenLoaded('updater')),
            'library' => new LibraryResource($this->whenLoaded('library')),
            'deleted_from_path' => $this->when($this->deleted_at !== null, $this->deleted_from_path),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
