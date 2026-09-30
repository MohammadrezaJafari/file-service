<?php

namespace Tests\Feature\Api;

use App\Models\Library;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LibraryAndNodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('blobs');
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_and_list_libraries(): void
    {
        $this->postJson('/api/v1/libraries', ['name' => 'Docs'])->assertCreated()->assertJsonPath('name', 'Docs');
        Library::factory()->create(); // someone else's

        $this->getJson('/api/v1/libraries')->assertOk()->assertJsonCount(1);
    }

    public function test_other_users_cannot_view_private_library(): void
    {
        $library = Library::factory()->create();

        $this->getJson("/api/v1/libraries/{$library->id}/nodes")->assertForbidden();
    }

    public function test_folder_creation_upload_and_listing(): void
    {
        $library = Library::factory()->create(['owner_id' => $this->user->id]);

        $folder = $this->postJson("/api/v1/libraries/{$library->id}/folders", ['name' => 'Photos'])
            ->assertCreated()->json();

        $file = UploadedFile::fake()->create('cat.jpg', 100, 'image/jpeg');
        $upload = $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => $file, 'parent_id' => $folder['id']])
            ->assertCreated()->json();

        $this->assertSame('cat.jpg', $upload['name']);
        $this->assertSame(1, $upload['version_number']);

        $listing = $this->getJson("/api/v1/libraries/{$library->id}/nodes?parent_id={$folder['id']}")->assertOk()->json();
        $this->assertCount(1, $listing['items']);
        $this->assertSame('rw', $listing['permission']);
        $this->assertSame([['id' => $folder['id'], 'name' => 'Photos']], $listing['breadcrumbs']);

        $library->refresh();
        $this->assertSame(1, $library->file_count);
        $this->assertSame(100 * 1024, $library->size_bytes);
        $this->assertSame(100 * 1024, $this->user->fresh()->used_bytes);
    }

    public function test_uploading_same_name_creates_new_version(): void
    {
        $library = Library::factory()->create(['owner_id' => $this->user->id]);

        $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'v1')])->assertCreated();
        $node = $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'v2 longer')])
            ->assertCreated()->json();

        $this->assertSame(2, $node['version_number']);
        $this->assertDatabaseCount('nodes', 1);
        $this->assertDatabaseCount('file_versions', 2);

        $versions = $this->getJson("/api/v1/nodes/{$node['id']}/versions")->assertOk()->json();
        $this->assertCount(2, $versions);

        $this->postJson("/api/v1/nodes/{$node['id']}/versions/{$versions[1]['id']}/restore")
            ->assertOk()->assertJsonPath('version_number', 3)->assertJsonPath('size', 2);

        $this->get("/api/v1/nodes/{$node['id']}/download")->assertOk()->assertStreamedContent('v1');
    }

    public function test_upload_without_replace_makes_unique_name(): void
    {
        $library = Library::factory()->create(['owner_id' => $this->user->id]);

        $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'v1')]);
        $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'v2'), 'replace' => 0])
            ->assertCreated()->assertJsonPath('name', 'a (1).txt');
    }

    public function test_rename_move_copy_and_delete(): void
    {
        $library = Library::factory()->create(['owner_id' => $this->user->id]);
        $a = Node::factory()->create(['library_id' => $library->id, 'name' => 'A']);
        $b = Node::factory()->create(['library_id' => $library->id, 'name' => 'B']);
        $file = $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('f.txt', 'hi'), 'parent_id' => $a->id])->json();

        $this->patchJson("/api/v1/nodes/{$file['id']}", ['name' => 'g.txt'])->assertOk()->assertJsonPath('name', 'g.txt');

        $this->postJson("/api/v1/nodes/{$file['id']}/move", ['target_parent_id' => $b->id])->assertOk()->assertJsonPath('parent_id', $b->id);

        $copy = $this->postJson("/api/v1/nodes/{$b->id}/copy", ['target_parent_id' => $a->id])->assertCreated()->json();
        $this->assertSame('B', $copy['name']);
        $this->assertSame(2, Node::files()->count());
        $this->assertSame(2, $library->fresh()->file_count);

        // moving a folder into itself is rejected
        $this->postJson("/api/v1/nodes/{$a->id}/move", ['target_parent_id' => $copy['id']])->assertUnprocessable();

        $this->deleteJson("/api/v1/nodes/{$b->id}")->assertNoContent();
        $this->assertSoftDeleted('nodes', ['id' => $b->id]);
        $this->assertSoftDeleted('nodes', ['id' => $file['id']]);

        $trash = $this->getJson("/api/v1/libraries/{$library->id}/trash")->assertOk()->json();
        $this->assertCount(1, $trash);
        $this->assertSame('/B', $trash[0]['deleted_from_path']);

        $this->postJson("/api/v1/libraries/{$library->id}/trash/{$b->id}/restore")->assertOk();
        $this->assertNull(Node::find($file['id'])->deleted_at);

        $this->deleteJson("/api/v1/nodes/{$b->id}");
        $this->deleteJson("/api/v1/libraries/{$library->id}/trash")->assertOk()->assertJsonPath('purged', 1);
        $this->assertDatabaseMissing('nodes', ['id' => $b->id]);
        $this->assertSame(1, $library->fresh()->file_count);
    }

    public function test_quota_is_enforced(): void
    {
        $this->user->update(['quota_bytes' => 1024]);
        $library = Library::factory()->create(['owner_id' => $this->user->id]);

        $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->create('big.bin', 2)])
            ->assertUnprocessable();
    }

    public function test_folder_download_returns_zip(): void
    {
        $library = Library::factory()->create(['owner_id' => $this->user->id]);
        $folder = Node::factory()->create(['library_id' => $library->id, 'name' => 'Z']);
        $this->post("/api/v1/libraries/{$library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('x.txt', 'zip me'), 'parent_id' => $folder->id]);

        $this->get("/api/v1/nodes/{$folder->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_search_and_star(): void
    {
        $library = Library::factory()->create(['owner_id' => $this->user->id]);
        $node = Node::factory()->create(['library_id' => $library->id, 'name' => 'Reports']);

        $this->getJson("/api/v1/libraries/{$library->id}/search?q=rep")->assertOk()->assertJsonCount(1);

        $this->postJson("/api/v1/nodes/{$node->id}/star")->assertOk();
        $this->getJson('/api/v1/starred')->assertOk()->assertJsonCount(1)->assertJsonPath('0.path', '/Reports');
        $this->deleteJson("/api/v1/nodes/{$node->id}/star")->assertOk();
        $this->getJson('/api/v1/starred')->assertJsonCount(0);
    }

    public function test_activities_are_recorded(): void
    {
        $library = $this->postJson('/api/v1/libraries', ['name' => 'Docs'])->json();
        $this->postJson("/api/v1/libraries/{$library['id']}/folders", ['name' => 'X']);

        $this->getJson('/api/v1/activities')->assertOk()->assertJsonCount(2, 'data');
    }
}
