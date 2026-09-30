<?php

use App\Models\Node;
use App\Services\BlobStorage;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(config('fileservice.frontend_url')));

// Admin-only raw file download used by the Filament panel.
Route::get('/admin/nodes/{node}/download', function (Node $node, BlobStorage $blobs) {
    abort_unless(auth()->user()?->is_admin, 403);
    abort_unless($node->isFile() && $node->storage_path && $blobs->exists($node->storage_path), 404);

    return $blobs->disk()->response($node->storage_path, $node->name, ['Content-Type' => $node->mime_type ?: 'application/octet-stream'], 'attachment');
})->middleware(['web', 'auth'])->name('admin.nodes.download');
