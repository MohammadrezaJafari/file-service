<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'path' => $this->path,
            'details' => $this->details,
            'user' => new UserResource($this->whenLoaded('user')),
            'library' => new LibraryResource($this->whenLoaded('library')),
            'node_id' => $this->node_id,
            'created_at' => $this->created_at,
        ];
    }
}
