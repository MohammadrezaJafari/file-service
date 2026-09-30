<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Http\Resources\ShareResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $groups = Group::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with('owner')
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group = Group::create($data + ['owner_id' => $request->user()->id]);
        $group->members()->attach($request->user()->id, ['role' => 'owner']);

        return (new GroupResource($group->load(['owner', 'members'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Group $group)
    {
        $this->authorize('view', $group);

        return new GroupResource($group->load(['owner', 'members']));
    }

    public function update(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $group->fill($data)->save();

        return new GroupResource($group->load(['owner', 'members']));
    }

    public function destroy(Request $request, Group $group)
    {
        $this->authorize('delete', $group);
        $group->delete();

        return response()->noContent();
    }

    public function libraries(Request $request, Group $group)
    {
        $this->authorize('view', $group);

        return ShareResource::collection($group->shares()->with(['library.owner', 'node', 'sharer'])->get());
    }

    public function addMember(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email'],
            'role' => ['nullable', Rule::in(['admin', 'member'])],
        ]);

        $user = ! empty($data['user_id']) ? User::find($data['user_id']) : User::where('email', $data['email'] ?? '')->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'User not found.']);
        }

        $group->members()->syncWithoutDetaching([$user->id => ['role' => $data['role'] ?? 'member']]);

        return new GroupResource($group->load(['owner', 'members']));
    }

    public function updateMember(Request $request, Group $group, User $user)
    {
        $this->authorize('update', $group);
        abort_if($user->id === $group->owner_id, 422, 'Cannot change the role of the group owner.');
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'member'])]]);
        $group->members()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return new GroupResource($group->load(['owner', 'members']));
    }

    public function removeMember(Request $request, Group $group, User $user)
    {
        $self = $request->user()->id === $user->id;
        if (! $self) {
            $this->authorize('update', $group);
        }
        abort_if($user->id === $group->owner_id, 422, 'The owner cannot leave the group.');
        $group->members()->detach($user->id);

        return response()->noContent();
    }
}
