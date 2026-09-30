<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileVersionResource;
use App\Http\Resources\LibraryResource;
use App\Http\Resources\NodeResource;
use App\Models\FileVersion;
use App\Models\Library;
use App\Models\Node;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BlobStorage;
use App\Services\LibraryCrypto;
use App\Services\NodeService;
use App\Services\ThumbnailService;
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
        protected LibraryCrypto $crypto,
        protected ThumbnailService $thumbs,
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
            ->when($request->query('tag_id'), fn ($q, $tagId) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tagId)))
            ->with(['updater', 'tags'])
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
            'is_unlocked' => $this->crypto->isUnlocked($library, $user),
            'tags' => $library->tags()->orderBy('name')->get(),
            'property_definitions' => $library->property_definitions ?? [],
            'items' => NodeResource::collection($children),
        ]);
    }

    public function show(Request $request, Node $node)
    {
        $this->authorize('view', $node);
        $node->resolved_path = $node->path();
        $node->is_starred = $request->user()->stars()->where('nodes.id', $node->id)->exists();

        return new NodeResource($node->load(['creator', 'updater', 'library.owner', 'tags']));
    }

    public function updateMetadata(Request $request, Node $node)
    {
        $this->authorize('write', $node);
        $data = $request->validate([
            'metadata' => ['nullable', 'array'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        if (array_key_exists('metadata', $data)) {
            $node->metadata = array_filter($data['metadata'] ?? [], fn ($v) => $v !== null && $v !== '');
            $node->save();
        }
        if (array_key_exists('tag_ids', $data)) {
            $valid = Tag::where('library_id', $node->library_id)->whereIn('id', $data['tag_ids'] ?? [])->pluck('id');
            $node->tags()->sync($valid);
        }
        $this->activity->log('file.metadata', $request->user(), $node->library, $node);

        return new NodeResource($node->fresh()->load('tags'));
    }

    /** Return the text content of a file (for the Markdown / whiteboard editors). */
    public function content(Request $request, Node $node)
    {
        $this->authorize('view', $node);
        abort_unless($node->isFile(), 400);
        abort_if($node->size > 5 * 1024 * 1024, 413, 'File too large to edit online.');
        $stream = $this->blobs->readStream($node->storage_path);
        abort_unless($stream, 404);
        $text = $node->is_encrypted
            ? $this->crypto->decryptToString($stream, $this->crypto->keyFor($node->library, $request->user()))
            : stream_get_contents($stream);
        fclose($stream);

        return response($text, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'X-Version' => $node->version_number]);
    }

    public function updateContent(Request $request, Node $node)
    {
        $this->authorize('write', $node);
        $data = $request->validate(['content' => ['present', 'string']]);

        return new NodeResource($this->nodes->updateContent($node, $data['content'], $request->user()));
    }

    public function storeFile(Request $request, Library $library)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'content' => ['nullable', 'string'],
            'mime_type' => ['nullable', 'string', 'max:100'],
        ]);
        $parent = $this->resolveParent($request, $library);
        $this->authorize('write', $parent ?? $library);

        $node = $this->nodes->createFile($library, $parent, $data['name'], $data['content'] ?? '', $request->user(), $data['mime_type'] ?? null);

        return (new NodeResource($node))->response()->setStatusCode(201);
    }

    public function thumbnail(Request $request, Node $node)
    {
        $this->authorize('view', $node);
        $jpeg = $this->thumbs->get($node, $request->user(), (int) $request->query('size', 256));
        abort_unless($jpeg, 404);

        return response($jpeg, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
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
            return $this->downloadFolder($node, $request->user());
        }

        $this->activity->log('file.download', $request->user(), $node->library, $node);

        return $this->streamFile($node->storage_path, $node->name, $node->mime_type, $request->boolean('inline'), $node->is_encrypted ? $this->crypto->keyFor($node->library, $request->user()) : null);
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
            'thumb' => $request->boolean('thumb') ? 1 : 0,
            'v' => $node->version_number,
            'u' => $request->user()->id,
        ]);

        return response()->json(['url' => $url]);
    }

    public function signedDownload(Request $request, Node $node)
    {
        $user = User::find($request->query('u'));
        if ($request->boolean('thumb')) {
            $jpeg = $this->thumbs->get($node, $user);
            abort_unless($jpeg, 404);

            return response($jpeg, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
        }
        if ($node->isFolder()) {
            return $this->downloadFolder($node, $user);
        }

        return $this->streamFile($node->storage_path, $node->name, $node->mime_type, $request->boolean('inline'), $node->is_encrypted ? $this->crypto->keyFor($node->library, $user) : null);
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

        return $this->streamFile($version->storage_path, $node->name, $version->mime_type, false, $version->is_encrypted ? $this->crypto->keyFor($node->library, $request->user()) : null);
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

    protected function streamFile(?string $path, string $name, ?string $mime, bool $inline = false, ?string $key = null): StreamedResponse
    {
        abort_if(! $path || ! $this->blobs->exists($path), 404, 'File content not found.');

        $disposition = $inline ? 'inline' : 'attachment';

        if ($key === null) {
            return $this->blobs->disk()->response($path, $name, [
                'Content-Type' => $mime ?: 'application/octet-stream',
            ], $disposition);
        }

        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $name);

        return response()->stream(function () use ($path, $key) {
            $stream = $this->blobs->readStream($path);
            $this->crypto->decryptStreamToOutput($stream, $key);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($name),
        ]);
    }

    protected function downloadFolder(Node $folder, ?User $user = null): StreamedResponse
    {
        $key = $folder->library->is_encrypted ? $this->crypto->keyFor($folder->library, $user) : null;
        $tmp = tempnam(sys_get_temp_dir(), 'fsz');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $this->addFolderToZip($zip, $folder, '', $key);
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

    protected function addFolderToZip(ZipArchive $zip, Node $folder, string $prefix, ?string $key = null): void
    {
        $zip->addEmptyDir($prefix === '' ? $folder->name : $prefix);
        $base = $prefix === '' ? $folder->name : $prefix;

        foreach ($folder->children as $child) {
            if ($child->isFolder()) {
                $this->addFolderToZip($zip, $child, $base.'/'.$child->name, $key);
            } elseif ($child->storage_path && ($abs = $this->blobs->absolutePath($child->storage_path)) && is_file($abs)) {
                if ($child->is_encrypted && $key) {
                    $stream = fopen($abs, 'rb');
                    $zip->addFromString($base.'/'.$child->name, $this->crypto->decryptToString($stream, $key));
                    fclose($stream);
                } else {
                    $zip->addFile($abs, $base.'/'.$child->name);
                }
            }
        }
    }
}
