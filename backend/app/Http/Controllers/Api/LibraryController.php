<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LibraryResource;
use App\Http\Resources\NodeResource;
use App\Models\Library;
use App\Models\Node;
use App\Models\Share;
use App\Services\ActivityLogger;
use App\Services\NodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LibraryController extends Controller
{
    public function __construct(protected NodeService $nodes, protected ActivityLogger $activity) {}

    public function index(Request $request)
    {
        $libraries = Library::query()
            ->where('owner_id', $request->user()->id)
            ->with('owner')
            ->orderBy('name')
            ->get();

        return LibraryResource::collection($libraries);
    }

    public function shared(Request $request)
    {
        $user = $request->user();
        $groupIds = $user->groups()->pluck('groups.id')->all();

        $shares = Share::query()
            ->with(['library.owner', 'node', 'sharer'])
            ->where(function ($q) use ($user, $groupIds) {
                $q->where('user_id', $user->id);
                if ($groupIds) {
                    $q->orWhereIn('group_id', $groupIds);
                }
            })
            ->whereHas('library')
            ->get();

        $items = $shares->map(function (Share $share) {
            $library = $share->library;
            $library->permission = $share->permission;
            if ($share->node) {
                $library->shared_folder = $share->node;
            }

            return [
                'share_id' => $share->id,
                'library' => new LibraryResource($library),
                'folder' => $share->node ? new NodeResource($share->node) : null,
                'permission' => $share->permission,
                'shared_by' => $share->sharer?->name,
                'via_group_id' => $share->group_id,
                'created_at' => $share->created_at,
            ];
        })->values();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ]);

        $library = Library::create([
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_encrypted' => ! empty($data['password']),
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
        ]);

        $this->activity->log('library.create', $request->user(), $library, null, '/');

        return (new LibraryResource($library->load('owner')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Library $library)
    {
        $this->authorize('view', $library);

        return new LibraryResource($library->load('owner'));
    }

    public function update(Request $request, Library $library)
    {
        $this->authorize('update', $library);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $library->fill($data)->save();
        $this->activity->log('library.update', $request->user(), $library, null, '/');

        return new LibraryResource($library->load('owner'));
    }

    public function destroy(Request $request, Library $library)
    {
        $this->authorize('delete', $library);

        $this->activity->log('library.delete', $request->user(), $library, null, '/');
        $library->delete();

        return response()->noContent();
    }

    public function trash(Request $request, Library $library)
    {
        $this->authorize('view', $library);

        $nodes = Node::onlyTrashed()
            ->where('library_id', $library->id)
            ->whereNotNull('deleted_by')
            ->orderByDesc('deleted_at')
            ->get();

        return NodeResource::collection($nodes);
    }

    public function restore(Request $request, Library $library, int $nodeId)
    {
        $this->authorize('write', $library);
        $node = Node::onlyTrashed()->where('library_id', $library->id)->whereNotNull('deleted_by')->findOrFail($nodeId);

        return (new NodeResource($this->nodes->restore($node, $request->user())))->response()->setStatusCode(200);
    }

    public function purge(Request $request, Library $library, int $nodeId)
    {
        $this->authorize('manage', $library);
        $node = Node::onlyTrashed()->where('library_id', $library->id)->whereNotNull('deleted_by')->findOrFail($nodeId);
        $this->nodes->purge($node, $request->user());

        return response()->noContent();
    }

    public function emptyTrash(Request $request, Library $library)
    {
        $this->authorize('manage', $library);
        $count = $this->nodes->emptyTrash($library, $request->user());

        return response()->json(['purged' => $count]);
    }
}
