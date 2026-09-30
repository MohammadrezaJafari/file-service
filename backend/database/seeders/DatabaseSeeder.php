<?php

namespace Database\Seeders;

use App\Models\Library;
use App\Models\Setting;
use App\Models\User;
use App\Services\NodeService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::defaults() as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => 'password', 'is_admin' => true],
        );

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Demo User', 'password' => 'password'],
        );

        if ($user->libraries()->count() === 0) {
            /** @var NodeService $nodes */
            $nodes = app(NodeService::class);

            $library = Library::create(['owner_id' => $user->id, 'name' => 'My Library', 'description' => 'Default personal library']);
            $docs = $nodes->createFolder($library, null, 'Documents', $user);
            $nodes->createFolder($library, null, 'Photos', $user);

            $readme = UploadedFile::fake()->createWithContent('README.md', "# Welcome\n\nThis is your first library.\n");
            $nodes->upload($library, null, $readme, $user);

            $notes = UploadedFile::fake()->createWithContent('notes.txt', "Some notes.\n");
            $nodes->upload($library, $docs, $notes, $user);
        }

        if ($admin->libraries()->count() === 0) {
            Library::create(['owner_id' => $admin->id, 'name' => 'Admin Library']);
        }
    }
}
