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
