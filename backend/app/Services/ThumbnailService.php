<?php

namespace App\Services;

use App\Models\Node;
use App\Models\User;

/**
 * Generates small JPEG previews for image files with GD. Plain-library thumbnails are
 * cached on the blob disk keyed by content hash; encrypted libraries are never cached.
 */
class ThumbnailService
{
    public function __construct(protected BlobStorage $blobs, protected LibraryCrypto $crypto) {}

    public function supports(Node $node): bool
    {
        return $node->isFile() && in_array($node->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    /** @return string|null JPEG bytes */
    public function get(Node $node, ?User $user, int $size = 256): ?string
    {
        if (! $this->supports($node) || ! $node->storage_path) {
            return null;
        }

        $cachePath = "thumbs/{$size}/{$node->hash}.jpg";
        if (! $node->is_encrypted && $this->blobs->disk()->exists($cachePath)) {
            return $this->blobs->disk()->get($cachePath);
        }

        $stream = $this->blobs->readStream($node->storage_path);
        if (! $stream) {
            return null;
        }
        $raw = $node->is_encrypted
            ? $this->crypto->decryptToString($stream, $this->crypto->keyFor($node->library, $user))
            : stream_get_contents($stream);
        fclose($stream);

        $jpeg = $this->resize($raw, $size);
        if ($jpeg && ! $node->is_encrypted) {
            $this->blobs->disk()->put($cachePath, $jpeg);
        }

        return $jpeg;
    }

    protected function resize(string $raw, int $size): ?string
    {
        $img = @imagecreatefromstring($raw);
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $size / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $thumb = imagecreatetruecolor($tw, $th);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        imagejpeg($thumb, null, 82);
        $out = ob_get_clean();
        imagedestroy($img);
        imagedestroy($thumb);

        return $out ?: null;
    }
}
