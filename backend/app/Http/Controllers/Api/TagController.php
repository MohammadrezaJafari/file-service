<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Library $library)
    {
        $this->authorize('view', $library);

        return response()->json($library->tags()->withCount('nodes')->orderBy('name')->get());
    }

    public function store(Request $request, Library $library)
    {
        $this->authorize('write', $library);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        if (! empty($data['parent_id'])) {
            Tag::where('library_id', $library->id)->findOrFail($data['parent_id']);
        }
        $tag = $library->tags()->firstOrCreate(
            ['parent_id' => $data['parent_id'] ?? null, 'name' => trim($data['name'])],
            ['color' => $data['color'] ?? '#1976D2'],
        );

        return response()->json($tag->loadCount('nodes'), 201);
    }

    public function update(Request $request, Tag $tag)
    {
        $this->authorize('write', $tag->library);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'color' => ['sometimes', 'string', 'max:20'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            abort_if($data['parent_id'] === $tag->id, 422, 'A tag cannot be its own parent.');
            Tag::where('library_id', $tag->library_id)->findOrFail($data['parent_id']);
        }
        $tag->update($data);

        return response()->json($tag->loadCount('nodes'));
    }

    public function destroy(Tag $tag)
    {
        $this->authorize('write', $tag->library);
        $tag->delete();

        return response()->noContent();
    }
}
