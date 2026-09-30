<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected bool $private = false;

    /**
     * Build a resource that always includes the private fields (used right after login/registration,
     * when the request is not yet authenticated).
     */
    public static function private(mixed $user): static
    {
        $resource = new static($user);
        $resource->private = true;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $isSelf = $this->private || $request->user()?->id === $this->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_path ? url('/storage/'.$this->avatar_path) : null,
            $this->mergeWhen($isSelf || $request->user()?->is_admin, [
                'is_admin' => $this->is_admin,
                'quota_bytes' => $this->effectiveQuota(),
                'used_bytes' => $this->used_bytes,
                'last_login_at' => $this->last_login_at,
                'created_at' => $this->created_at,
            ]),
        ];
    }
}
