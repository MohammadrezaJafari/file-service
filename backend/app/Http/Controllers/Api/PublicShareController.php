<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NodeResource;
use App\Models\Node;
use App\Models\ShareLink;
use App\Services\ActivityLogger;
use App\Services\BlobStorage;
use App\Services\NodeService;
use Illuminate\Http\Request;

/**
 * Anonymous access to share links. The link password (when set) is passed via the
 * `X-Share-Password` header or the `password` query/body parameter.
 */
class PublicShareController extends Controller
{
    public function __construct(
        protected NodeService $nodes,
        protected BlobStorage $blobs,
        protected ActivityLogger $activity,
    ) {}

    public function show(Request $request, string $token)
    {
        $link = $this->resolve($token);
        $root = $link->node;

        $payload = [
            'token' => $link->token,
            'kind' => $link->kind,
            'name' => $root?->name ?? $link->library->name,
            'type' => $root?->type ?? 'folder',
            'has_password' => $link->hasPassword(),
            'expires_at' => $link->expires_at,
            'allow_download' => $link->allow_download,
            'owner' => $link->creator->name,
        ];

        if (! $link->checkPassword($this->password($request))) {
            return response()->json($payload + ['locked' => true]);
        }

        $link->increment('view_count');

        if ($root && $root->isFile()) {
            $payload['file'] = new NodeResource($root);
        }

        return response()->json($payload + ['locked' => false]);
    }

    public function verify(Request $request, string $token)
    {
        $link = $this->resolve($token);
        $ok = $link->checkPassword($request->input('password'));

        return response()->json(['ok' => $ok], $ok ? 200 : 403);
    }

    public function browse(Request $request, string $token)
    {
        $link = $this->resolve($token, $request);
        abort_if($link->isUploadLink(), 403);

        $folder = $this->resolveFolder($link, $request->query('folder_id'));

        $children = Node::query()
            ->where('library_id', $link->library_id)
            ->where('parent_id', $folder?->id)
            ->orderByRaw("case when type = 'folder' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        $breadcrumbs = [];
        if ($folder && $link->node_id !== $folder->id) {
            $stop = false;
            foreach ([...$folder->ancestors(), $folder] as $n) {
                if ($link->node_id === null || $stop || $n->id === $link->node_id) {
                    $stop = $stop || $n->id === $link->node_id;
                    if ($n->id !== $link->node_id) {
                        $breadcrumbs[] = ['id' => $n->id, 'name' => $n->name];
                    }
                }
            }
        }

        return response()->json([
            'folder' => $folder ? new NodeResource($folder) : null,
            'breadcrumbs' => $breadcrumbs,
            'items' => NodeResource::collection($children),
        ]);
    }

    public function download(Request $request, string $token)
    {
        $link = $this->resolve($token, $request);
        abort_if($link->isUploadLink(), 403);
        abort_unless($link->allow_download, 403, 'Downloads are disabled for this link.');

        $node = $request->query('node_id')
            ? $this->resolveNode($link, (int) $request->query('node_id'))
            : $link->node;

        abort_unless($node && $node->isFile(), 404);

        $link->increment('download_count');
        $this->activity->log('link.download', null, $link->library, $node, null, ['token' => $link->token]);

        return $this->blobs->disk()->response($node->storage_path, $node->name, [
            'Content-Type' => $node->mime_type ?: 'application/octet-stream',
        ], $request->boolean('inline') ? 'inline' : 'attachment');
    }

    public function upload(Request $request, string $token)
    {
        $link = $this->resolve($token, $request);
        abort_unless($link->isUploadLink(), 403, 'This link does not accept uploads.');

        $request->validate(['file' => ['required', 'file']]);

        $node = $this->nodes->upload($link->library, $link->node, $request->file('file'), $link->creator, null, false);
        $this->activity->log('link.upload', null, $link->library, $node, null, ['token' => $link->token]);

        return (new NodeResource($node))->response()->setStatusCode(201);
    }

    // ---------------------------------------------------------------------

    protected function resolve(string $token, ?Request $request = null): ShareLink
    {
        $link = ShareLink::with(['library', 'node', 'creator'])->where('token', $token)->first();

        abort_if(! $link || $link->isExpired() || ! $link->library || ($link->node_id && ! $link->node), 404, 'Share link not found or expired.');

        if ($request && ! $link->checkPassword($this->password($request))) {
            abort(403, 'Password required.');
        }

        return $link;
    }

    protected function password(Request $request): ?string
    {
        return $request->header('X-Share-Password') ?? $request->input('password');
    }

    protected function resolveFolder(ShareLink $link, mixed $folderId): ?Node
    {
        if (! $folderId) {
            return $link->node;
        }

        $folder = $this->resolveNode($link, (int) $folderId);
        abort_unless($folder->isFolder(), 404);

        return $folder;
    }

    protected function resolveNode(ShareLink $link, int $nodeId): Node
    {
        $node = Node::where('library_id', $link->library_id)->findOrFail($nodeId);

        if ($link->node_id !== null && $node->id !== $link->node_id && ! $node->isDescendantOf($link->node)) {
            abort(404);
        }

        return $node;
    }
}
