<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\Library;
use App\Models\Node;
use App\Policies\GroupPolicy;
use App\Policies\LibraryPolicy;
use App\Policies\NodePolicy;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Gate::policy(Library::class, LibraryPolicy::class);
        Gate::policy(Node::class, NodePolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
    }
}
