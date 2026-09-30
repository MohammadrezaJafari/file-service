<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LibraryResource;
use App\Http\Resources\NodeResource;
use App\Models\Library;
use App\Models\Node;
use App\Models\Share;
use App\Services\ActivityLogger;
use App\Services\LibraryCrypto;
use App\Services\NodeService;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function __construct(protected NodeService $nodes, protected ActivityLogger $activity, protected LibraryCrypto $crypto) {}

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

        $library = new Library([
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $this->crypto->setupLibrary($library, $data['password']);
        }
        $library->save();

        if ($library->is_encrypted) {
            $this->crypto->unlock($library, $request->user(), $data['password']);
        }

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

    public function unlock(Request $request, Library $library)
    {
        $this->authorize('view', $library);
        $data = $request->validate(['password' => ['required', 'string']]);
        $this->crypto->unlock($library, $request->user(), $data['password']);
        $this->activity->log('library.unlock', $request->user(), $library, null, '/');

        return response()->json(['unlocked' => true, 'expires_in_minutes' => LibraryCrypto::UNLOCK_TTL_MINUTES]);
    }

    public function lock(Request $request, Library $library)
    {
        $this->crypto->lock($library, $request->user());

        return response()->json(['unlocked' => false]);
    }

    public function changePassword(Request $request, Library $library)
    {
        $this->authorize('manage', $library);
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:6', 'max:100']]);
        $this->crypto->changePassword($library, $data['current_password'], $data['password']);
        $this->crypto->unlock($library, $request->user(), $data['password']);

        return response()->noContent();
    }

    /** Replace the custom property definitions of the library. */
    public function updateProperties(Request $request, Library $library)
    {
        $this->authorize('manage', $library);
        $data = $request->validate([
            'properties' => ['present', 'array', 'max:50'],
            'properties.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'properties.*.label' => ['required', 'string', 'max:100'],
            'properties.*.type' => ['required', 'in:text,number,date,select,checkbox,user'],
            'properties.*.options' => ['nullable', 'array'],
            'properties.*.options.*' => ['string', 'max:100'],
        ]);
        $library->property_definitions = array_values($data['properties']);
        $library->save();

        return new LibraryResource($library->load('owner'));
    }

    /** Aggregate statistics for the library (files by type, size, uploads over time). */
    public function stats(Request $request, Library $library)
    {
        $this->authorize('view', $library);
        $files = Node::where('library_id', $library->id)->files()->get(['mime_type', 'size', 'created_at', 'name']);
        $byType = $files->groupBy(fn ($f) => self::category($f->mime_type, $f->name))
            ->map(fn ($g, $k) => ['type' => $k, 'count' => $g->count(), 'size' => (int) $g->sum('size')])
            ->values();
        $byMonth = $files->groupBy(fn ($f) => $f->created_at?->format('Y-m'))->map(fn ($g, $k) => ['month' => $k, 'count' => $g->count(), 'size' => (int) $g->sum('size')])->sortKeys()->values();
        $largest = $files->sortByDesc('size')->take(10)->values()->map(fn ($f) => ['name' => $f->name, 'size' => $f->size]);

        return response()->json([
            'file_count' => $files->count(),
            'folder_count' => Node::where('library_id', $library->id)->folders()->count(),
            'size_bytes' => (int) $files->sum('size'),
            'trash_count' => Node::onlyTrashed()->where('library_id', $library->id)->whereNotNull('deleted_by')->count(),
            'by_type' => $byType,
            'by_month' => $byMonth,
            'largest' => $largest,
        ]);
    }

    public static function category(?string $mime, string $name): string
    {
        $mime = (string) $mime;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($mime === 'application/pdf' || in_array($ext, ['doc', 'docx', 'odt', 'xls', 'xlsx', 'ppt', 'pptx', 'md', 'txt'])) {
            return 'document';
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'archive';
        }
        if (in_array($ext, ['js', 'ts', 'php', 'py', 'json', 'html', 'css', 'vue', 'sh', 'sql'])) {
            return 'code';
        }

        return 'other';
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
