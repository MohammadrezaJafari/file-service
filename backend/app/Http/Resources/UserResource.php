<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSelf = $request->user()?->id === $this->id;

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
