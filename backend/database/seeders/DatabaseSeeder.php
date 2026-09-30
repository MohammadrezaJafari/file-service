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
            ['name' => 'مدیر سیستم', 'password' => 'password', 'is_admin' => true],
        );

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'کاربر نمونه', 'password' => 'password'],
        );

        if ($user->libraries()->count() === 0) {
            /** @var NodeService $nodes */
            $nodes = app(NodeService::class);

            $library = Library::create(['owner_id' => $user->id, 'name' => 'کتابخانه من', 'description' => 'کتابخانه شخصی پیش‌فرض']);
            $docs = $nodes->createFolder($library, null, 'اسناد', $user);
            $nodes->createFolder($library, null, 'عکس‌ها', $user);

            $readme = UploadedFile::fake()->createWithContent('README.md', "# خوش آمدید\n\nاین اولین کتابخانه شماست.\n");
            $nodes->upload($library, null, $readme, $user);

            $notes = UploadedFile::fake()->createWithContent('notes.txt', "چند یادداشت.\n");
            $nodes->upload($library, $docs, $notes, $user);
        }

        if ($admin->libraries()->count() === 0) {
            Library::create(['owner_id' => $admin->id, 'name' => 'کتابخانه مدیر']);
        }
    }
}
