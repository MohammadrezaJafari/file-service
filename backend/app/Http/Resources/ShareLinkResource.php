<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShareLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = rtrim(config('fileservice.frontend_url'), '/');
        $prefix = $this->kind === 'upload' ? '/u/' : '/s/';

        return [
            'id' => $this->id,
            'token' => $this->token,
            'url' => $base.'/#'.$prefix.$this->token,
            'kind' => $this->kind,
            'library_id' => $this->library_id,
            'node_id' => $this->node_id,
            'library' => new LibraryResource($this->whenLoaded('library')),
            'node' => new NodeResource($this->whenLoaded('node')),
            'has_password' => $this->hasPassword(),
            'expires_at' => $this->expires_at,
            'is_expired' => $this->isExpired(),
            'allow_download' => $this->allow_download,
            'view_count' => $this->view_count,
            'download_count' => $this->download_count,
            'created_at' => $this->created_at,
        ];
    }
}
