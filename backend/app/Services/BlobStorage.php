<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Content-addressable blob store. Identical file contents are stored once.
 */
class BlobStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('fileservice.disk', 'blobs'));
    }

    /**
     * @return array{path: string, hash: string, size: int}
     */
    public function putUploadedFile(UploadedFile $file): array
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $this->pathForHash($hash);

        if (! $this->disk()->exists($path)) {
            $this->disk()->putFileAs(dirname($path), $file, basename($path));
        }

        return ['path' => $path, 'hash' => $hash, 'size' => $file->getSize()];
    }

    /**
     * @return array{path: string, hash: string, size: int}
     */
    public function putContents(string $contents): array
    {
        $hash = hash('sha256', $contents);
        $path = $this->pathForHash($hash);

        if (! $this->disk()->exists($path)) {
            $this->disk()->put($path, $contents);
        }

        return ['path' => $path, 'hash' => $hash, 'size' => strlen($contents)];
    }

    public function pathForHash(string $hash): string
    {
        return 'blobs/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function readStream(string $path)
    {
        return $this->disk()->readStream($path);
    }

    public function absolutePath(string $path): ?string
    {
        try {
            return $this->disk()->path($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
