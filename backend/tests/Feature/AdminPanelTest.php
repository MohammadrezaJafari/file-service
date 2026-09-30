<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Groups\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Libraries\Pages\EditLibrary;
use App\Filament\Resources\Libraries\RelationManagers\NodesRelationManager;
use App\Filament\Resources\Libraries\RelationManagers\SharesRelationManager;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Group;
use App\Models\Library;
use App\Models\Node;
use App\Models\Setting;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $library = Library::factory()->create();
        Node::factory()->create(['library_id' => $library->id]);
        $group = Group::factory()->create();
        ShareLink::create(['library_id' => $library->id, 'created_by' => $admin->id]);
        Setting::set('site_name', 'Test Cloud');

        $this->actingAs($admin);

        foreach (['/admin', '/admin/users', '/admin/users/create', "/admin/users/{$admin->id}", '/admin/libraries', "/admin/libraries/{$library->id}/edit", '/admin/groups', "/admin/groups/{$group->id}/edit", '/admin/share-links', '/admin/activities', '/admin/settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_settings_page_saves(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm(['site_name' => 'Renamed', 'registration_enabled' => false, 'share_links_enabled' => true, 'default_quota_mb' => 512, 'max_upload_mb' => 100])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', Setting::get('site_name'));
        $this->assertSame((string) (512 * 1048576), Setting::get('default_quota_bytes'));
        $this->assertSame('0', Setting::get('registration_enabled'));
    }

    public function test_admin_can_create_user_with_quota(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm(['name' => 'New', 'email' => 'new@example.com', 'password' => 'password123', 'is_admin' => false, 'is_active' => true, 'quota_mb' => 10])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'quota_bytes' => 10 * 1048576]);
    }

    public function test_library_relation_managers_render(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $library = Library::factory()->create();
        $folder = Node::factory()->create(['library_id' => $library->id, 'name' => 'Docs']);
        Node::factory()->create(['library_id' => $library->id, 'parent_id' => $folder->id, 'type' => 'file', 'name' => 'a.txt', 'size' => 10, 'storage_path' => 'x']);
        $group = Group::factory()->create();
        $library->shares()->create(['shared_by' => $admin->id, 'group_id' => $group->id, 'permission' => 'rw']);

        Livewire::actingAs($admin)
            ->test(NodesRelationManager::class, ['ownerRecord' => $library, 'pageClass' => EditLibrary::class])
            ->assertOk()
            ->assertSee('a.txt')
            ->assertSee('Docs');

        Livewire::actingAs($admin)
            ->test(SharesRelationManager::class, ['ownerRecord' => $library, 'pageClass' => EditLibrary::class])
            ->assertOk()
            ->assertSee($group->name);

        Livewire::actingAs($admin)
            ->test(MembersRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditGroup::class])
            ->assertOk();
    }
}
