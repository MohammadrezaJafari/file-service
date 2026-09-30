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

class AdvancedFeaturesTest extends TestCase
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

    public function test_encrypted_library_lifecycle(): void
    {
        $lib = $this->postJson('/api/v1/libraries', ['name' => 'Secret', 'password' => 'hunter22'])->assertCreated()->json();
        $this->assertTrue($lib['is_encrypted']);
        $this->assertTrue($lib['is_unlocked']);

        $node = $this->post("/api/v1/libraries/{$lib['id']}/upload", ['file' => UploadedFile::fake()->createWithContent('s.txt', 'top secret')])->assertCreated()->json();
        $this->assertTrue($node['is_encrypted']);

        // blob on disk is not the plaintext
        $path = Node::find($node['id'])->storage_path;
        $this->assertStringNotContainsString('top secret', Storage::disk('blobs')->get($path));

        $this->get("/api/v1/nodes/{$node['id']}/download")->assertOk()->assertStreamedContent('top secret');
        $this->get("/api/v1/nodes/{$node['id']}/content")->assertOk()->assertSee('top secret');

        // lock -> access denied with 423
        $this->postJson("/api/v1/libraries/{$lib['id']}/lock")->assertOk();
        $this->get("/api/v1/nodes/{$node['id']}/download")->assertStatus(423);
        $this->getJson("/api/v1/libraries/{$lib['id']}/nodes")->assertOk()->assertJsonPath('is_unlocked', false);

        // wrong then right password
        $this->postJson("/api/v1/libraries/{$lib['id']}/unlock", ['password' => 'nope'])->assertUnprocessable();
        $this->postJson("/api/v1/libraries/{$lib['id']}/unlock", ['password' => 'hunter22'])->assertOk();
        $this->get("/api/v1/nodes/{$node['id']}/download")->assertOk()->assertStreamedContent('top secret');

        // change password keeps data readable
        $this->putJson("/api/v1/libraries/{$lib['id']}/password", ['current_password' => 'hunter22', 'password' => 'newpass1'])->assertNoContent();
        $this->postJson("/api/v1/libraries/{$lib['id']}/lock");
        $this->postJson("/api/v1/libraries/{$lib['id']}/unlock", ['password' => 'newpass1'])->assertOk();
        $this->get("/api/v1/nodes/{$node['id']}/download")->assertOk()->assertStreamedContent('top secret');

        // public links are refused for encrypted libraries
        $this->postJson('/api/v1/share-links', ['library_id' => $lib['id'], 'node_id' => $node['id']])->assertUnprocessable();

        // signed URL works while unlocked
        $url = $this->getJson("/api/v1/nodes/{$node['id']}/download-url")->json('url');
        app('auth')->forgetGuards();
        $this->get($url)->assertOk()->assertStreamedContent('top secret');
    }

    public function test_tags_hierarchy_and_filtering(): void
    {
        $lib = Library::factory()->create(['owner_id' => $this->user->id]);
        $a = Node::factory()->create(['library_id' => $lib->id, 'name' => 'A']);
        $b = Node::factory()->create(['library_id' => $lib->id, 'name' => 'B']);

        $parent = $this->postJson("/api/v1/libraries/{$lib->id}/tags", ['name' => 'Project', 'color' => '#f00'])->assertCreated()->json();
        $child = $this->postJson("/api/v1/libraries/{$lib->id}/tags", ['name' => 'Alpha', 'parent_id' => $parent['id']])->assertCreated()->json();
        $this->assertSame($parent['id'], $child['parent_id']);

        $this->patchJson("/api/v1/nodes/{$a->id}/metadata", ['tag_ids' => [$child['id']]])->assertOk()->assertJsonCount(1, 'tags');

        $this->getJson("/api/v1/libraries/{$lib->id}/nodes?tag_id={$child['id']}")->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $a->id);
        $this->getJson("/api/v1/libraries/{$lib->id}/nodes")->assertJsonCount(2, 'items')->assertJsonCount(2, 'tags');
        $this->getJson("/api/v1/libraries/{$lib->id}/tags")->assertOk()->assertJsonPath('1.nodes_count', 0);

        $this->deleteJson("/api/v1/tags/{$parent['id']}")->assertNoContent();
        $this->assertDatabaseCount('tags', 0); // cascade
        $this->assertDatabaseCount('node_tag', 0);
        $this->assertNotNull(Node::find($b->id));
    }

    public function test_custom_properties_and_metadata(): void
    {
        $lib = Library::factory()->create(['owner_id' => $this->user->id]);
        $props = [
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['todo', 'doing', 'done']],
            ['key' => 'deadline', 'label' => 'Deadline', 'type' => 'date'],
        ];
        $this->putJson("/api/v1/libraries/{$lib->id}/properties", ['properties' => $props])->assertOk()->assertJsonCount(2, 'property_definitions');
        $this->putJson("/api/v1/libraries/{$lib->id}/properties", ['properties' => [['key' => 'Bad Key', 'label' => 'x', 'type' => 'text']]])->assertUnprocessable();

        $node = Node::factory()->create(['library_id' => $lib->id, 'name' => 'task.md', 'type' => 'file']);
        $this->patchJson("/api/v1/nodes/{$node->id}/metadata", ['metadata' => ['status' => 'doing', 'deadline' => '2026-12-01', 'empty' => '']])
            ->assertOk()->assertJsonPath('metadata.status', 'doing')->assertJsonMissingPath('metadata.empty');

        // read-only sharee cannot edit metadata
        $other = User::factory()->create();
        $lib->shares()->create(['shared_by' => $this->user->id, 'user_id' => $other->id, 'permission' => 'r']);
        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/nodes/{$node->id}/metadata", ['metadata' => ['status' => 'done']])->assertForbidden();
    }

    public function test_create_and_edit_text_files_with_versions(): void
    {
        $lib = Library::factory()->create(['owner_id' => $this->user->id]);
        $page = $this->postJson("/api/v1/libraries/{$lib->id}/files", ['name' => 'Home.md', 'content' => '# Hello'])->assertCreated()->json();
        $this->assertSame('text/markdown', $page['mime_type']);
        $this->assertSame(7, $page['size']);

        $this->get("/api/v1/nodes/{$page['id']}/content")->assertOk()->assertHeader('X-Version', '1')->assertSee('# Hello');
        $this->putJson("/api/v1/nodes/{$page['id']}/content", ['content' => '# Hello world'])->assertOk()->assertJsonPath('version_number', 2)->assertJsonPath('size', 13);
        $this->getJson("/api/v1/nodes/{$page['id']}/versions")->assertJsonCount(2);
        $this->assertSame(13, $lib->fresh()->size_bytes);

        $this->postJson("/api/v1/libraries/{$lib->id}/files", ['name' => 'Home.md'])->assertUnprocessable();
    }

    public function test_thumbnail_generation(): void
    {
        $lib = Library::factory()->create(['owner_id' => $this->user->id]);
        $img = imagecreatetruecolor(800, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        $node = $this->post("/api/v1/libraries/{$lib->id}/upload", ['file' => UploadedFile::fake()->createWithContent('red.png', $png)])->assertCreated()->json();
        $this->assertTrue($node['has_thumbnail']);

        $res = $this->get("/api/v1/nodes/{$node['id']}/thumbnail?size=128")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $thumb = imagecreatefromstring($res->getContent());
        $this->assertSame(128, imagesx($thumb));
        $this->assertSame(96, imagesy($thumb));
        $this->assertTrue(Storage::disk('blobs')->exists("thumbs/128/{$node['hash']}.jpg"));
    }

    public function test_library_stats(): void
    {
        $lib = Library::factory()->create(['owner_id' => $this->user->id]);
        $this->post("/api/v1/libraries/{$lib->id}/upload", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $this->post("/api/v1/libraries/{$lib->id}/upload", ['file' => UploadedFile::fake()->create('b.jpg', 20, 'image/jpeg')]);
        Node::factory()->create(['library_id' => $lib->id, 'name' => 'F']);

        $stats = $this->getJson("/api/v1/libraries/{$lib->id}/stats")->assertOk()->json();
        $this->assertSame(2, $stats['file_count']);
        $this->assertSame(1, $stats['folder_count']);
        $this->assertSame(30 * 1024, $stats['size_bytes']);
        $this->assertCount(2, $stats['by_type']);
        $this->assertSame('b.jpg', $stats['largest'][0]['name']);
    }
}
