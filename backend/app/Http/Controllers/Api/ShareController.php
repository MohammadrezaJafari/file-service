<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShareResource;
use App\Models\Group;
use App\Models\Library;
use App\Models\Node;
use App\Models\Share;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShareController extends Controller
{
    public function __construct(protected ActivityLogger $activity) {}

    public function index(Request $request, Library $library)
    {
        $this->authorize('manage', $library);

        $shares = $library->shares()->with(['user', 'group', 'node', 'sharer'])->orderByDesc('id')->get();

        return ShareResource::collection($shares);
    }

    public function store(Request $request, Library $library)
    {
        $this->authorize('manage', $library);

        $data = $request->validate([
            'node_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_email' => ['nullable', 'email'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'permission' => ['required', Rule::in([Share::PERM_READ, Share::PERM_READ_WRITE])],
        ]);

        if (! empty($data['user_email'])) {
            $target = User::where('email', $data['user_email'])->first();
            if (! $target) {
                throw ValidationException::withMessages(['user_email' => 'No user with that email.']);
            }
            $data['user_id'] = $target->id;
        }

        if (empty($data['user_id']) === empty($data['group_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Provide exactly one of user or group.']);
        }

        if (! empty($data['user_id']) && $data['user_id'] === $request->user()->id) {
            throw ValidationException::withMessages(['user_id' => 'You cannot share with yourself.']);
        }

        if (! empty($data['group_id'])) {
            $group = Group::findOrFail($data['group_id']);
            $this->authorize('view', $group);
        }

        $node = null;
        if (! empty($data['node_id'])) {
            $node = Node::where('library_id', $library->id)->folders()->findOrFail($data['node_id']);
        }

        $share = Share::updateOrCreate(
            [
                'library_id' => $library->id,
                'node_id' => $node?->id,
                'user_id' => $data['user_id'] ?? null,
                'group_id' => $data['group_id'] ?? null,
            ],
            ['shared_by' => $request->user()->id, 'permission' => $data['permission']],
        );

        $this->activity->log('share.create', $request->user(), $library, $node, $node?->path() ?? '/', [
            'user_id' => $share->user_id, 'group_id' => $share->group_id, 'permission' => $share->permission,
        ]);

        return (new ShareResource($share->load(['user', 'group', 'node', 'sharer'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Share $share)
    {
        $this->authorize('manage', $share->library);
        $data = $request->validate(['permission' => ['required', Rule::in([Share::PERM_READ, Share::PERM_READ_WRITE])]]);
        $share->update($data);

        return new ShareResource($share->load(['user', 'group', 'node', 'sharer']));
    }

    public function destroy(Request $request, Share $share)
    {
        $user = $request->user();
        $isRecipient = $share->user_id === $user->id;
        if (! $isRecipient) {
            $this->authorize('manage', $share->library);
        }

        $this->activity->log('share.delete', $user, $share->library, $share->node, $share->node?->path() ?? '/');
        $share->delete();

        return response()->noContent();
    }
}
