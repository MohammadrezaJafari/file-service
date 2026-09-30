<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Library;
use App\Models\Share;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $groupIds = $user->groups()->pluck('groups.id')->all();

        $libraryIds = Library::where('owner_id', $user->id)->pluck('id')
            ->merge(Share::where('user_id', $user->id)->orWhereIn('group_id', $groupIds ?: [0])->pluck('library_id'))
            ->unique()
            ->values();

        $activities = Activity::query()
            ->with(['user', 'library'])
            ->where(fn ($q) => $q->whereIn('library_id', $libraryIds)->orWhere('user_id', $user->id))
            ->when($request->query('library_id'), fn ($q, $id) => $q->where('library_id', $id))
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return ActivityResource::collection($activities);
    }
}
