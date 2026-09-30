<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'owner_id' => $this->owner_id,
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'my_role' => $user ? $this->roleOf($user) : null,
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'role' => $m->id === $this->owner_id ? 'owner' : $m->pivot->role,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
