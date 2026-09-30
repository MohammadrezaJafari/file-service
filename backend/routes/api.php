<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\PublicShareController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\ShareLinkController;
use App\Http\Controllers\Api\StarController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('settings', [AuthController::class, 'settings']);
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

    // Signed, short-lived download URLs issued to authenticated users
    Route::get('dl/{node}', [NodeController::class, 'signedDownload'])->middleware('signed')->name('nodes.signed-download');

    // Anonymous share link access
    Route::prefix('share/{token}')->middleware('throttle:120,1')->group(function () {
        Route::get('/', [PublicShareController::class, 'show']);
        Route::post('verify', [PublicShareController::class, 'verify']);
        Route::get('browse', [PublicShareController::class, 'browse']);
        Route::get('download', [PublicShareController::class, 'download']);
        Route::post('upload', [PublicShareController::class, 'upload']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/me', [AuthController::class, 'update']);
        Route::put('auth/password', [AuthController::class, 'updatePassword']);

        Route::get('users/search', [UserController::class, 'search']);

        Route::get('libraries', [LibraryController::class, 'index']);
        Route::get('libraries/shared', [LibraryController::class, 'shared']);
        Route::post('libraries', [LibraryController::class, 'store']);
        Route::get('libraries/{library}', [LibraryController::class, 'show']);
        Route::put('libraries/{library}', [LibraryController::class, 'update']);
        Route::delete('libraries/{library}', [LibraryController::class, 'destroy']);

        Route::get('libraries/{library}/trash', [LibraryController::class, 'trash']);
        Route::post('libraries/{library}/trash/{nodeId}/restore', [LibraryController::class, 'restore']);
        Route::delete('libraries/{library}/trash/{nodeId}', [LibraryController::class, 'purge']);
        Route::delete('libraries/{library}/trash', [LibraryController::class, 'emptyTrash']);

        Route::get('libraries/{library}/nodes', [NodeController::class, 'index']);
        Route::post('libraries/{library}/folders', [NodeController::class, 'storeFolder']);
        Route::post('libraries/{library}/upload', [NodeController::class, 'upload']);
        Route::get('libraries/{library}/search', [NodeController::class, 'search']);

        Route::get('libraries/{library}/shares', [ShareController::class, 'index']);
        Route::post('libraries/{library}/shares', [ShareController::class, 'store']);
        Route::put('shares/{share}', [ShareController::class, 'update']);
        Route::delete('shares/{share}', [ShareController::class, 'destroy']);

        Route::get('nodes/{node}', [NodeController::class, 'show']);
        Route::patch('nodes/{node}', [NodeController::class, 'update']);
        Route::post('nodes/{node}/move', [NodeController::class, 'move']);
        Route::post('nodes/{node}/copy', [NodeController::class, 'copy']);
        Route::delete('nodes/{node}', [NodeController::class, 'destroy']);
        Route::get('nodes/{node}/download', [NodeController::class, 'download']);
        Route::get('nodes/{node}/download-url', [NodeController::class, 'downloadUrl']);
        Route::get('nodes/{node}/versions', [NodeController::class, 'versions']);
        Route::get('nodes/{node}/versions/{version}/download', [NodeController::class, 'downloadVersion']);
        Route::post('nodes/{node}/versions/{version}/restore', [NodeController::class, 'restoreVersion']);
        Route::post('nodes/{node}/star', [StarController::class, 'store']);
        Route::delete('nodes/{node}/star', [StarController::class, 'destroy']);

        Route::get('starred', [StarController::class, 'index']);

        Route::get('share-links', [ShareLinkController::class, 'index']);
        Route::post('share-links', [ShareLinkController::class, 'store']);
        Route::delete('share-links/{shareLink}', [ShareLinkController::class, 'destroy']);

        Route::get('groups', [GroupController::class, 'index']);
        Route::post('groups', [GroupController::class, 'store']);
        Route::get('groups/{group}', [GroupController::class, 'show']);
        Route::put('groups/{group}', [GroupController::class, 'update']);
        Route::delete('groups/{group}', [GroupController::class, 'destroy']);
        Route::get('groups/{group}/libraries', [GroupController::class, 'libraries']);
        Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
        Route::put('groups/{group}/members/{user}', [GroupController::class, 'updateMember']);
        Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);

        Route::get('activities', [ActivityController::class, 'index']);
    });
});
