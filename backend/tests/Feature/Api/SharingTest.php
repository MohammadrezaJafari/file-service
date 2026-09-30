<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Library;
use App\Models\Node;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $other;

    protected Library $library;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('blobs');
        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->library = Library::factory()->create(['owner_id' => $this->owner->id]);
    }

    public function test_share_library_with_user_read_only(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/libraries/{$this->library->id}/shares", ['user_email' => $this->other->email, 'permission' => 'r'])
            ->assertCreated();

        Sanctum::actingAs($this->other);
        $this->getJson("/api/v1/libraries/{$this->library->id}/nodes")->assertOk()->assertJsonPath('permission', 'r');
        $this->postJson("/api/v1/libraries/{$this->library->id}/folders", ['name' => 'nope'])->assertForbidden();

        $shared = $this->getJson('/api/v1/libraries/shared')->assertOk()->json('data');
        $this->assertCount(1, $shared);
        $this->assertSame('r', $shared[0]['permission']);
    }

    public function test_share_folder_with_group_read_write(): void
    {
        $group = Group::factory()->create(['owner_id' => $this->owner->id]);
        $group->members()->attach($this->other->id, ['role' => 'member']);
        $folder = Node::factory()->create(['library_id' => $this->library->id, 'name' => 'Team']);
        $private = Node::factory()->create(['library_id' => $this->library->id, 'name' => 'Private']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/libraries/{$this->library->id}/shares", ['group_id' => $group->id, 'node_id' => $folder->id, 'permission' => 'rw'])
            ->assertCreated();

        Sanctum::actingAs($this->other);
        $this->getJson("/api/v1/libraries/{$this->library->id}/nodes?parent_id={$folder->id}")->assertOk()->assertJsonPath('permission', 'rw');
        $this->postJson("/api/v1/libraries/{$this->library->id}/folders", ['name' => 'Sub', 'parent_id' => $folder->id])->assertCreated();
        $this->getJson("/api/v1/libraries/{$this->library->id}/nodes?parent_id={$private->id}")->assertForbidden();
        $this->getJson("/api/v1/libraries/{$this->library->id}/nodes")->assertForbidden();
    }

    public function test_recipient_can_remove_share_and_owner_can_list(): void
    {
        Sanctum::actingAs($this->owner);
        $share = $this->postJson("/api/v1/libraries/{$this->library->id}/shares", ['user_id' => $this->other->id, 'permission' => 'rw'])->json();
        $this->getJson("/api/v1/libraries/{$this->library->id}/shares")->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($this->other);
        $this->deleteJson("/api/v1/shares/{$share['id']}")->assertNoContent();
        $this->getJson("/api/v1/libraries/{$this->library->id}/nodes")->assertForbidden();
    }

    public function test_public_download_link_with_password_and_expiry(): void
    {
        Sanctum::actingAs($this->owner);
        $file = $this->post("/api/v1/libraries/{$this->library->id}/upload", ['file' => UploadedFile::fake()->createWithContent('pub.txt', 'public!')])->json();

        $link = $this->postJson('/api/v1/share-links', ['library_id' => $this->library->id, 'node_id' => $file['id'], 'password' => 'pw1234', 'expires_in_days' => 3])
            ->assertCreated()->json();
        $this->assertTrue($link['has_password']);
        $this->assertStringContainsString('/#/s/'.$link['token'], $link['url']);

        $this->getJson('/api/v1/share-links')->assertOk()->assertJsonCount(1);

        // anonymous
        $this->flushHeaders();
        app('auth')->forgetGuards();
        $this->getJson("/api/v1/share/{$link['token']}")->assertOk()->assertJsonPath('locked', true);
        $this->postJson("/api/v1/share/{$link['token']}/verify", ['password' => 'bad'])->assertForbidden();
        $this->postJson("/api/v1/share/{$link['token']}/verify", ['password' => 'pw1234'])->assertOk();
        $this->get("/api/v1/share/{$link['token']}/download")->assertForbidden();
        $this->withHeader('X-Share-Password', 'pw1234')->get("/api/v1/share/{$link['token']}/download")->assertOk()->assertStreamedContent('public!');
        $this->assertSame(1, ShareLink::first()->download_count);

        ShareLink::query()->update(['expires_at' => now()->subDay()]);
        $this->getJson("/api/v1/share/{$link['token']}")->assertNotFound();
    }

    public function test_public_folder_link_browsing_is_scoped(): void
    {
        $shared = Node::factory()->create(['library_id' => $this->library->id, 'name' => 'Shared']);
        $inner = Node::factory()->create(['library_id' => $this->library->id, 'parent_id' => $shared->id, 'name' => 'Inner']);
        $outside = Node::factory()->create(['library_id' => $this->library->id, 'name' => 'Outside']);

        Sanctum::actingAs($this->owner);
        $link = $this->postJson('/api/share-links'.'', [])->assertNotFound();
        $link = $this->postJson('/api/v1/share-links', ['library_id' => $this->library->id, 'node_id' => $shared->id])->assertCreated()->json();

        app('auth')->forgetGuards();
        $this->getJson("/api/v1/share/{$link['token']}/browse")->assertOk()->assertJsonCount(1, 'items');
        $this->getJson("/api/v1/share/{$link['token']}/browse?folder_id={$inner->id}")->assertOk()->assertJsonPath('breadcrumbs.0.name', 'Inner');
        $this->getJson("/api/v1/share/{$link['token']}/browse?folder_id={$outside->id}")->assertNotFound();
    }

    public function test_upload_link_accepts_anonymous_files(): void
    {
        $inbox = Node::factory()->create(['library_id' => $this->library->id, 'name' => 'Inbox']);

        Sanctum::actingAs($this->owner);
        $link = $this->postJson('/api/v1/share-links', ['library_id' => $this->library->id, 'node_id' => $inbox->id, 'kind' => 'upload'])->assertCreated()->json();

        app('auth')->forgetGuards();
        $this->post("/api/v1/share/{$link['token']}/upload", ['file' => UploadedFile::fake()->createWithContent('drop.txt', 'dropped')])
            ->assertCreated()->assertJsonPath('parent_id', $inbox->id);
        $this->get("/api/v1/share/{$link['token']}/download")->assertForbidden();
    }

    public function test_group_lifecycle(): void
    {
        Sanctum::actingAs($this->owner);
        $group = $this->postJson('/api/v1/groups', ['name' => 'Team'])->assertCreated()->json();
        $this->postJson("/api/v1/groups/{$group['id']}/members", ['email' => $this->other->email])->assertOk()->assertJsonCount(2, 'members');
        $this->putJson("/api/v1/groups/{$group['id']}/members/{$this->other->id}", ['role' => 'admin'])->assertOk();

        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/groups')->assertOk()->assertJsonCount(1)->assertJsonPath('0.my_role', 'admin');
        $this->deleteJson("/api/v1/groups/{$group['id']}")->assertForbidden();
        $this->deleteJson("/api/v1/groups/{$group['id']}/members/{$this->other->id}")->assertNoContent();
        $this->getJson("/api/v1/groups/{$group['id']}")->assertForbidden();
    }
}
