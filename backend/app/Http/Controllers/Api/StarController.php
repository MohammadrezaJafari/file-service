<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NodeResource;
use App\Models\Node;
use Illuminate\Http\Request;

class StarController extends Controller
{
    public function index(Request $request)
    {
        $nodes = $request->user()->stars()
            ->with('library.owner')
            ->whereHas('library')
            ->orderByDesc('stars.created_at')
            ->get()
            ->each(function (Node $n) {
                $n->resolved_path = $n->path();
                $n->is_starred = true;
            });

        return NodeResource::collection($nodes);
    }

    public function store(Request $request, Node $node)
    {
        $this->authorize('view', $node);
        $request->user()->stars()->syncWithoutDetaching([$node->id]);

        return response()->json(['starred' => true]);
    }

    public function destroy(Request $request, Node $node)
    {
        $request->user()->stars()->detach($node->id);

        return response()->json(['starred' => false]);
    }
}
