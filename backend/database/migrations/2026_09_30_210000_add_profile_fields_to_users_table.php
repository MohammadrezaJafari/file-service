<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->unsignedBigInteger('quota_bytes')->nullable()->after('is_active');
            $table->unsignedBigInteger('used_bytes')->default(0)->after('quota_bytes');
            $table->string('avatar_path')->nullable()->after('used_bytes');
            $table->timestamp('last_login_at')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_active', 'quota_bytes', 'used_bytes', 'avatar_path', 'last_login_at']);
        });
    }
};
