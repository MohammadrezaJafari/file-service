<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('libraries', function (Blueprint $table) {
            // Library key (random 32 bytes) sealed with a key derived from the user's password.
            $table->text('encrypted_key')->nullable()->after('password_hash');
            $table->string('key_salt', 64)->nullable()->after('encrypted_key');
            // Definitions of custom file properties: [{key,label,type,options}]
            $table->json('property_definitions')->nullable()->after('file_count');
        });

        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('hash');
            $table->json('metadata')->nullable()->after('version_number');
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('hash');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tags')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#1976D2');
            $table->timestamps();

            $table->unique(['library_id', 'parent_id', 'name']);
        });

        Schema::create('node_tag', function (Blueprint $table) {
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['node_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_tag');
        Schema::dropIfExists('tags');
        Schema::table('file_versions', fn (Blueprint $t) => $t->dropColumn('is_encrypted'));
        Schema::table('nodes', fn (Blueprint $t) => $t->dropColumn(['is_encrypted', 'metadata']));
        Schema::table('libraries', fn (Blueprint $t) => $t->dropColumn(['encrypted_key', 'key_salt', 'property_definitions']));
    }
};
