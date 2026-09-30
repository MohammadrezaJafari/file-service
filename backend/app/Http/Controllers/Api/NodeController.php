<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileVersionResource;
use App\Http\Resources\LibraryResource;
use App\Http\Resources\NodeResource;
use App\Models\FileVersion;
use App\Models\Library;
use App\Models\Node;
use App\Services\ActivityLogger;
use App\Services\BlobStorage;
use App\Services\NodeService;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class NodeController extends Controller
{
    public function __construct(
        protected NodeService $nodes,
        protected BlobStorage $blobs,
        protected ActivityLogger $activity,
    ) {}

    /**
     * List the contents of a folder (or the library root when parent_id is omitted).
     */
    public function index(Request $request, Library $library)
    {
        $parent = $this->resolveParent($request, $library);
        $this->authorize('view', $parent ?? $library);

        $user = $request->user();
        $starred = $user->stars()->where('library_id', $library->id)->pluck('nodes.id')->flip();

        $children = Node::query()
            ->where('library_id', $library->id)
            ->where('parent_id', $parent?->id)
            ->with(['updater'])
            ->orderByRaw("case when type = 'folder' then 0 else 1 end")
            ->orderBy('name')
            ->get()
            ->each(fn (Node $n) => $n->is_starred = isset($starred[$n->id]));

        $breadcrumbs = $parent ? array_map(fn (Node $n) => ['id' => $n->id, 'name' => $n->name], [...$parent->ancestors(), $parent]) : [];

        return response()->json([
            'library' => new LibraryResource($library->load('owner')),
            'folder' => $parent ? new NodeResource($parent) : null,
            'breadcrumbs' => $breadcrumbs,
            'permission' => $parent ? Permission::forNode($user, $parent) : Permission::forLibrary($user, $library),
            'items' => NodeResource::collection($children),
        ]);
    }

    public function show(Request $request, Node $node)
    {
        $this->authorize('view', $node);
        $node->resolved_path = $node->path();
        $node->is_starred = $request->user()->stars()->where('nodes.id', $node->id)->exists();

        return new NodeResource($node->load(['creator', 'updater', 'library.owner']));
    }

    public function storeFolder(Request $request, Library $library)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $parent = $this->resolveParent($request, $library);
        $this->authorize('write', $parent ?? $library);

        $node = $this->nodes->createFolder($library, $parent, $data['name'], $request->user());

        return (new NodeResource($node))->response()->setStatusCode(201);
    }

    public function upload(Request $request, Library $library)
    {
        $request->validate([
            'file' => ['required', 'file'],
            'parent_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'replace' => ['nullable', 'boolean'],
        ]);
        $parent = $this->resolveParent($request, $library);
        $this->authorize('write', $parent ?? $library);

        $node = $this->nodes->upload(
            $library,
            $parent,
            $request->file('file'),
            $request->user(),
            $request->input('name'),
            $request->boolean('replace', true),
        );

        return (new NodeResource($node))->response()->setStatusCode(201);
    }

    public function update(Request $request, Node $node)
    {
        $this->authorize('write', $node);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        return new NodeResource($this->nodes->rename($node, $data['name'], $request->user()));
    }

    public function move(Request $request, Node $node)
    {
        [$library, $parent] = $this->resolveTarget($request, $node);
        $this->authorize('write', $node);
        $this->authorize('write', $parent ?? $library);

        return new NodeResource($this->nodes->move($node, $library, $parent, $request->user()));
    }

    public function copy(Request $request, Node $node)
    {
        [$library, $parent] = $this->resolveTarget($request, $node);
        $this->authorize('view', $node);
        $this->authorize('write', $parent ?? $library);

        return new NodeResource($this->nodes->copy($node, $library, $parent, $request->user()));
    }

    public function destroy(Request $request, Node $node)
    {
        $this->authorize('write', $node);
        $this->nodes->trash($node, $request->user());

        return response()->noContent();
    }

    public function download(Request $request, Node $node)
    {
        $this->authorize('view', $node);

        if ($node->isFolder()) {
            return $this->downloadFolder($node);
        }

        $this->activity->log('file.download', $request->user(), $node->library, $node);

        return $this->streamFile($node->storage_path, $node->name, $node->mime_type, $request->boolean('inline'));
    }

    /**
     * Issue a short-lived signed URL so browsers can download/preview without the bearer token.
     */
    public function downloadUrl(Request $request, Node $node)
    {
        $this->authorize('view', $node);

        $url = URL::temporarySignedRoute('nodes.signed-download', now()->addMinutes(30), [
            'node' => $node->id,
            'inline' => $request->boolean('inline') ? 1 : 0,
            'v' => $node->version_number,
        ]);

        return response()->json(['url' => $url]);
    }

    public function signedDownload(Request $request, Node $node)
    {
        if ($node->isFolder()) {
            return $this->downloadFolder($node);
        }

        return $this->streamFile($node->storage_path, $node->name, $node->mime_type, $request->boolean('inline'));
    }

    public function versions(Node $node)
    {
        $this->authorize('view', $node);
        abort_unless($node->isFile(), 400, 'Folders do not have versions.');

        return FileVersionResource::collection($node->versions()->with('creator')->get());
    }

    public function downloadVersion(Request $request, Node $node, FileVersion $version)
    {
        $this->authorize('view', $node);
        abort_unless($version->node_id === $node->id, 404);

        return $this->streamFile($version->storage_path, $node->name, $version->mime_type);
    }

    public function restoreVersion(Request $request, Node $node, FileVersion $version)
    {
        $this->authorize('write', $node);
        abort_unless($version->node_id === $node->id, 404);

        return new NodeResource($this->nodes->restoreVersion($node, $version, $request->user()));
    }

    public function search(Request $request, Library $library)
    {
        $this->authorize('view', $library);
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return NodeResource::collection(collect());
        }

        $results = $this->nodes->search($library, $q)->each(fn (Node $n) => $n->resolved_path = $n->path());

        return NodeResource::collection($results);
    }

    // ---------------------------------------------------------------------

    protected function resolveParent(Request $request, Library $library): ?Node
    {
        $parentId = $request->input('parent_id', $request->query('parent_id'));
        if (! $parentId) {
            return null;
        }

        return Node::where('library_id', $library->id)->folders()->findOrFail($parentId);
    }

    /**
     * @return array{0: Library, 1: ?Node}
     */
    protected function resolveTarget(Request $request, Node $node): array
    {
        $data = $request->validate([
            'target_library_id' => ['nullable', 'integer'],
            'target_parent_id' => ['nullable', 'integer'],
        ]);

        $library = isset($data['target_library_id']) ? Library::findOrFail($data['target_library_id']) : $node->library;
        $parent = isset($data['target_parent_id'])
            ? Node::where('library_id', $library->id)->folders()->findOrFail($data['target_parent_id'])
            : null;

        return [$library, $parent];
    }

    protected function streamFile(?string $path, string $name, ?string $mime, bool $inline = false): StreamedResponse
    {
        abort_if(! $path || ! $this->blobs->exists($path), 404, 'File content not found.');

        $disposition = $inline ? 'inline' : 'attachment';

        return $this->blobs->disk()->response($path, $name, [
            'Content-Type' => $mime ?: 'application/octet-stream',
        ], $disposition);
    }

    protected function downloadFolder(Node $folder): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fsz');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $this->addFolderToZip($zip, $folder, '');
        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            $handle = fopen($tmp, 'rb');
            while (! feof($handle)) {
                echo fread($handle, 1024 * 1024);
            }
            fclose($handle);
            @unlink($tmp);
        }, $folder->name.'.zip', ['Content-Type' => 'application/zip']);
    }

    protected function addFolderToZip(ZipArchive $zip, Node $folder, string $prefix): void
    {
        $zip->addEmptyDir($prefix === '' ? $folder->name : $prefix);
        $base = $prefix === '' ? $folder->name : $prefix;

        foreach ($folder->children as $child) {
            if ($child->isFolder()) {
                $this->addFolderToZip($zip, $child, $base.'/'.$child->name);
            } elseif ($child->storage_path && ($abs = $this->blobs->absolutePath($child->storage_path)) && is_file($abs)) {
                $zip->addFile($abs, $base.'/'.$child->name);
            }
        }
    }
}
