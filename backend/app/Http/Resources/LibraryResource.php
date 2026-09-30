<?php

namespace App\Http\Resources;

use App\Services\LibraryCrypto;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_encrypted' => $this->is_encrypted,
            'is_unlocked' => $user ? app(LibraryCrypto::class)->isUnlocked($this->resource, $user) : false,
            'property_definitions' => $this->property_definitions ?? [],
            'size_bytes' => $this->size_bytes,
            'file_count' => $this->file_count,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'owner_id' => $this->owner_id,
            'is_owner' => $user?->id === $this->owner_id,
            'permission' => $this->permission ?? ($user ? Permission::forLibrary($user, $this->resource) : null),
            'shared_folder' => $this->when(isset($this->shared_folder), fn () => new NodeResource($this->shared_folder)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
