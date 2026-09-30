<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'node_id' => $this->node_id,
            'permission' => $this->permission,
            'library' => new LibraryResource($this->whenLoaded('library')),
            'node' => new NodeResource($this->whenLoaded('node')),
            'user' => new UserResource($this->whenLoaded('user')),
            'group' => new GroupResource($this->whenLoaded('group')),
            'sharer' => new UserResource($this->whenLoaded('sharer')),
            'created_at' => $this->created_at,
        ];
    }
}
