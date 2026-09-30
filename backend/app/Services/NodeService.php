<?php

namespace App\Services;

use App\Models\FileVersion;
use App\Models\Library;
use App\Models\Node;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NodeService
{
    public function __construct(
        protected BlobStorage $blobs,
        protected ActivityLogger $activity,
    ) {}

    public function createFolder(Library $library, ?Node $parent, string $name, User $user): Node
    {
        $this->assertParent($library, $parent);
        $name = $this->sanitizeName($name);
        $this->assertNameAvailable($library, $parent, $name);

        $node = Node::create([
            'library_id' => $library->id,
            'parent_id' => $parent?->id,
            'type' => Node::TYPE_FOLDER,
            'name' => $name,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->activity->log('folder.create', $user, $library, $node);

        return $node;
    }

    /**
     * Upload a file. If a file with the same name already exists in the parent, a new version is created.
     */
    public function upload(Library $library, ?Node $parent, UploadedFile $file, User $user, ?string $name = null, bool $replace = true): Node
    {
        $this->assertParent($library, $parent);
        $name = $this->sanitizeName($name ?: $file->getClientOriginalName());

        $max = (int) Setting::get('max_upload_bytes', 0);
        if ($max > 0 && $file->getSize() > $max) {
            throw ValidationException::withMessages(['file' => "File exceeds the maximum upload size of {$max} bytes."]);
        }

        $owner = $library->owner;
        $existing = Node::query()
            ->where('library_id', $library->id)
            ->where('parent_id', $parent?->id)
            ->where('name', $name)
            ->first();

        if ($existing && $existing->isFolder()) {
            throw ValidationException::withMessages(['name' => "A folder named '{$name}' already exists here."]);
        }

        if ($existing && ! $replace) {
            $name = $this->uniqueName($library, $parent, $name);
            $existing = null;
        }

        $delta = $file->getSize() - ($existing?->size ?? 0);
        if ($delta > 0 && ! $owner->hasSpaceFor($delta)) {
            throw ValidationException::withMessages(['file' => 'Storage quota exceeded.']);
        }

        $stored = $this->blobs->putUploadedFile($file);
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        return DB::transaction(function () use ($library, $parent, $name, $user, $existing, $stored, $mime, $delta, $owner) {
            if ($existing) {
                $node = $existing;
                $node->fill([
                    'size' => $stored['size'],
                    'mime_type' => $mime,
                    'storage_path' => $stored['path'],
                    'hash' => $stored['hash'],
                    'version_number' => $node->version_number + 1,
                    'updated_by' => $user->id,
                ])->save();
                $action = 'file.update';
            } else {
                $node = Node::create([
                    'library_id' => $library->id,
                    'parent_id' => $parent?->id,
                    'type' => Node::TYPE_FILE,
                    'name' => $name,
                    'size' => $stored['size'],
                    'mime_type' => $mime,
                    'storage_path' => $stored['path'],
                    'hash' => $stored['hash'],
                    'version_number' => 1,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $library->increment('file_count');
                $action = 'file.create';
            }

            FileVersion::create([
                'node_id' => $node->id,
                'version_number' => $node->version_number,
                'storage_path' => $stored['path'],
                'size' => $stored['size'],
                'mime_type' => $mime,
                'hash' => $stored['hash'],
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            $this->adjustUsage($library, $owner, $delta);
            $this->activity->log($action, $user, $library, $node, null, ['size' => $stored['size']]);

            return $node->fresh();
        });
    }

    public function rename(Node $node, string $name, User $user): Node
    {
        $name = $this->sanitizeName($name);
        if ($name === $node->name) {
            return $node;
        }
        $this->assertNameAvailable($node->library, $node->parent, $name, $node);

        $old = $node->path();
        $node->fill(['name' => $name, 'updated_by' => $user->id])->save();
        $this->activity->log($node->isFolder() ? 'folder.rename' : 'file.rename', $user, $node->library, $node, $node->path(), ['from' => $old]);

        return $node;
    }

    public function move(Node $node, Library $targetLibrary, ?Node $targetParent, User $user): Node
    {
        $this->assertParent($targetLibrary, $targetParent);
        $this->assertNotIntoSelf($node, $targetParent);

        $sameLibrary = $node->library_id === $targetLibrary->id;
        $oldPath = $node->path();
        $name = $this->uniqueName($targetLibrary, $targetParent, $node->name, $node);

        return DB::transaction(function () use ($node, $targetLibrary, $targetParent, $user, $sameLibrary, $oldPath, $name) {
            if (! $sameLibrary) {
                $size = $this->subtreeSize($node);
                $count = $this->subtreeFileCount($node);
                $oldLibrary = $node->library;

                if ($oldLibrary->owner_id !== $targetLibrary->owner_id && ! $targetLibrary->owner->hasSpaceFor($size)) {
                    throw ValidationException::withMessages(['target' => 'Storage quota exceeded on target library.']);
                }

                $this->adjustUsage($oldLibrary, $oldLibrary->owner, -$size);
                $oldLibrary->decrement('file_count', $count);
                $this->adjustUsage($targetLibrary, $targetLibrary->owner, $size);
                $targetLibrary->increment('file_count', $count);
                $this->relabelLibrary($node, $targetLibrary->id);
            }

            $node->fill([
                'library_id' => $targetLibrary->id,
                'parent_id' => $targetParent?->id,
                'name' => $name,
                'updated_by' => $user->id,
            ])->save();

            $this->activity->log($node->isFolder() ? 'folder.move' : 'file.move', $user, $targetLibrary, $node, $node->path(), ['from' => $oldPath]);

            return $node->fresh();
        });
    }

    public function copy(Node $node, Library $targetLibrary, ?Node $targetParent, User $user): Node
    {
        $this->assertParent($targetLibrary, $targetParent);
        $this->assertNotIntoSelf($node, $targetParent);

        $size = $this->subtreeSize($node);
        if (! $targetLibrary->owner->hasSpaceFor($size)) {
            throw ValidationException::withMessages(['target' => 'Storage quota exceeded on target library.']);
        }

        return DB::transaction(function () use ($node, $targetLibrary, $targetParent, $user, $size) {
            $name = $this->uniqueName($targetLibrary, $targetParent, $node->name);
            $copy = $this->copyRecursive($node, $targetLibrary, $targetParent, $name, $user);
            $this->adjustUsage($targetLibrary, $targetLibrary->owner, $size);
            $targetLibrary->increment('file_count', $this->subtreeFileCount($node));
            $this->activity->log($node->isFolder() ? 'folder.copy' : 'file.copy', $user, $targetLibrary, $copy, $copy->path(), ['from' => $node->path()]);

            return $copy;
        });
    }

    public function trash(Node $node, User $user): void
    {
        DB::transaction(function () use ($node, $user) {
            $path = $node->path();
            $node->fill(['deleted_by' => $user->id, 'deleted_from_path' => $path])->save();
            $this->trashRecursive($node, $user);
            $this->activity->log($node->isFolder() ? 'folder.delete' : 'file.delete', $user, $node->library, $node, $path);
        });
    }

    public function restore(Node $node, User $user): Node
    {
        return DB::transaction(function () use ($node, $user) {
            $parent = $node->parent()->withTrashed()->first();
            $targetParent = ($parent && $parent->trashed()) ? null : $parent;

            $name = $this->uniqueName($node->library, $targetParent, $node->name);
            $node->fill(['parent_id' => $targetParent?->id, 'name' => $name, 'deleted_by' => null, 'deleted_from_path' => null]);
            $node->restore();
            $this->restoreRecursive($node);
            $this->activity->log($node->isFolder() ? 'folder.restore' : 'file.restore', $user, $node->library, $node);

            return $node->fresh();
        });
    }

    public function purge(Node $node, ?User $user = null): void
    {
        DB::transaction(function () use ($node, $user) {
            $library = $node->library;
            $size = $this->subtreeSize($node, withTrashed: true);
            $count = $this->subtreeFileCount($node, withTrashed: true);

            $this->activity->log($node->isFolder() ? 'folder.purge' : 'file.purge', $user, $library, null, $node->deleted_from_path ?? $node->path());

            $node->forceDelete();
            $this->adjustUsage($library, $library->owner, -$size);
            $library->decrement('file_count', min($count, $library->file_count));
        });
    }

    public function emptyTrash(Library $library, ?User $user = null): int
    {
        $roots = Node::onlyTrashed()
            ->where('library_id', $library->id)
            ->whereNotNull('deleted_by')
            ->get();

        foreach ($roots as $node) {
            if (Node::onlyTrashed()->find($node->id)) {
                $this->purge($node, $user);
            }
        }

        return $roots->count();
    }

    public function restoreVersion(Node $node, FileVersion $version, User $user): Node
    {
        if ($version->node_id !== $node->id) {
            throw ValidationException::withMessages(['version' => 'Version does not belong to this file.']);
        }

        return DB::transaction(function () use ($node, $version, $user) {
            $delta = $version->size - $node->size;
            $owner = $node->library->owner;
            if ($delta > 0 && ! $owner->hasSpaceFor($delta)) {
                throw ValidationException::withMessages(['version' => 'Storage quota exceeded.']);
            }

            $node->fill([
                'size' => $version->size,
                'mime_type' => $version->mime_type,
                'storage_path' => $version->storage_path,
                'hash' => $version->hash,
                'version_number' => $node->version_number + 1,
                'updated_by' => $user->id,
            ])->save();

            FileVersion::create([
                'node_id' => $node->id,
                'version_number' => $node->version_number,
                'storage_path' => $version->storage_path,
                'size' => $version->size,
                'mime_type' => $version->mime_type,
                'hash' => $version->hash,
                'created_by' => $user->id,
                'comment' => "Restored from version {$version->version_number}",
                'created_at' => now(),
            ]);

            $this->adjustUsage($node->library, $owner, $delta);
            $this->activity->log('file.restore_version', $user, $node->library, $node, null, ['version' => $version->version_number]);

            return $node->fresh();
        });
    }

    public function search(Library $library, string $query, int $limit = 100)
    {
        return Node::query()
            ->where('library_id', $library->id)
            ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%')
            ->orderBy('type')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    // ---------------------------------------------------------------------

    public function sanitizeName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            throw ValidationException::withMessages(['name' => 'Invalid name.']);
        }
        if (mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'Name is too long.']);
        }

        return $name;
    }

    protected function assertParent(Library $library, ?Node $parent): void
    {
        if ($parent === null) {
            return;
        }
        if ($parent->library_id !== $library->id) {
            throw ValidationException::withMessages(['parent_id' => 'Parent folder does not belong to this library.']);
        }
        if (! $parent->isFolder()) {
            throw ValidationException::withMessages(['parent_id' => 'Parent must be a folder.']);
        }
    }

    protected function assertNotIntoSelf(Node $node, ?Node $target): void
    {
        if ($target === null) {
            return;
        }
        if ($target->id === $node->id || $target->isDescendantOf($node)) {
            throw ValidationException::withMessages(['target' => 'Cannot move or copy a folder into itself.']);
        }
    }

    protected function assertNameAvailable(Library $library, ?Node $parent, string $name, ?Node $except = null): void
    {
        $exists = Node::query()
            ->where('library_id', $library->id)
            ->where('parent_id', $parent?->id)
            ->where('name', $name)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => "An item named '{$name}' already exists here."]);
        }
    }

    public function uniqueName(Library $library, ?Node $parent, string $name, ?Node $except = null): string
    {
        $taken = Node::query()
            ->where('library_id', $library->id)
            ->where('parent_id', $parent?->id)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->pluck('name')
            ->flip();

        if (! isset($taken[$name])) {
            return $name;
        }

        $dot = strrpos($name, '.');
        $base = $dot !== false && $dot > 0 ? substr($name, 0, $dot) : $name;
        $ext = $dot !== false && $dot > 0 ? substr($name, $dot) : '';

        for ($i = 1; $i < 10000; $i++) {
            $candidate = "{$base} ({$i}){$ext}";
            if (! isset($taken[$candidate])) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['name' => 'Could not find a unique name.']);
    }

    public function subtreeSize(Node $node, bool $withTrashed = false): int
    {
        if ($node->isFile()) {
            return $node->size;
        }
        $total = 0;
        $children = $withTrashed ? $node->children()->withTrashed()->get() : $node->children()->get();
        foreach ($children as $child) {
            $total += $this->subtreeSize($child, $withTrashed);
        }

        return $total;
    }

    public function subtreeFileCount(Node $node, bool $withTrashed = false): int
    {
        if ($node->isFile()) {
            return 1;
        }
        $total = 0;
        $children = $withTrashed ? $node->children()->withTrashed()->get() : $node->children()->get();
        foreach ($children as $child) {
            $total += $this->subtreeFileCount($child, $withTrashed);
        }

        return $total;
    }

    protected function adjustUsage(Library $library, User $owner, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $library->size_bytes = max(0, $library->size_bytes + $delta);
        $library->save();
        $owner->used_bytes = max(0, $owner->used_bytes + $delta);
        $owner->save();
    }

    protected function relabelLibrary(Node $node, int $libraryId): void
    {
        foreach ($node->children()->withTrashed()->get() as $child) {
            $child->library_id = $libraryId;
            $child->save();
            $this->relabelLibrary($child, $libraryId);
        }
    }

    protected function copyRecursive(Node $source, Library $library, ?Node $parent, string $name, User $user): Node
    {
        $copy = Node::create([
            'library_id' => $library->id,
            'parent_id' => $parent?->id,
            'type' => $source->type,
            'name' => $name,
            'size' => $source->size,
            'mime_type' => $source->mime_type,
            'storage_path' => $source->storage_path,
            'hash' => $source->hash,
            'version_number' => $source->isFile() ? 1 : 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if ($source->isFile()) {
            FileVersion::create([
                'node_id' => $copy->id,
                'version_number' => 1,
                'storage_path' => $source->storage_path,
                'size' => $source->size,
                'mime_type' => $source->mime_type,
                'hash' => $source->hash,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        } else {
            foreach ($source->children as $child) {
                $this->copyRecursive($child, $library, $copy, $child->name, $user);
            }
        }

        return $copy;
    }

    protected function trashRecursive(Node $node, User $user): void
    {
        $node->delete();
        foreach ($node->children as $child) {
            $this->trashRecursive($child, $user);
        }
    }

    protected function restoreRecursive(Node $node): void
    {
        foreach ($node->children()->onlyTrashed()->whereNull('deleted_by')->get() as $child) {
            $child->restore();
            $this->restoreRecursive($child);
        }
    }
}
