<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShareLinkResource;
use App\Models\Library;
use App\Models\Node;
use App\Models\Setting;
use App\Models\ShareLink;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ShareLinkController extends Controller
{
    public function __construct(protected ActivityLogger $activity) {}

    public function index(Request $request)
    {
        $links = ShareLink::query()
            ->where('created_by', $request->user()->id)
            ->with(['library', 'node'])
            ->orderByDesc('id')
            ->get();

        return ShareLinkResource::collection($links);
    }

    public function store(Request $request)
    {
        abort_unless((bool) Setting::get('share_links_enabled', '1'), 403, 'Share links are disabled.');

        $data = $request->validate([
            'library_id' => ['required', 'integer', 'exists:libraries,id'],
            'node_id' => ['nullable', 'integer'],
            'kind' => ['nullable', Rule::in([ShareLink::KIND_DOWNLOAD, ShareLink::KIND_UPLOAD])],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'allow_download' => ['nullable', 'boolean'],
        ]);

        $library = Library::findOrFail($data['library_id']);
        abort_if($library->is_encrypted, 422, 'Encrypted libraries cannot be shared with public links.');
        $node = null;
        if (! empty($data['node_id'])) {
            $node = Node::where('library_id', $library->id)->findOrFail($data['node_id']);
            $this->authorize('share', $node);
        } else {
            $this->authorize('write', $library);
        }

        $kind = $data['kind'] ?? ShareLink::KIND_DOWNLOAD;
        if ($kind === ShareLink::KIND_UPLOAD && $node && ! $node->isFolder()) {
            abort(422, 'Upload links must point to a folder.');
        }

        $link = ShareLink::create([
            'library_id' => $library->id,
            'node_id' => $node?->id,
            'created_by' => $request->user()->id,
            'kind' => $kind,
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'expires_at' => ! empty($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null,
            'allow_download' => $data['allow_download'] ?? true,
        ]);

        $this->activity->log('link.create', $request->user(), $library, $node, $node?->path() ?? '/', ['kind' => $kind]);

        return (new ShareLinkResource($link->load(['library', 'node'])))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, ShareLink $shareLink)
    {
        $user = $request->user();
        abort_unless($user->is_admin || $shareLink->created_by === $user->id || $shareLink->library->owner_id === $user->id, 403);

        $this->activity->log('link.delete', $user, $shareLink->library, $shareLink->node, $shareLink->node?->path() ?? '/');
        $shareLink->delete();

        return response()->noContent();
    }
}
