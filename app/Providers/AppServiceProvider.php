<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\Post;
use App\Models\Species;
use App\Observers\AuthorObserver;
use App\Observers\PostObserver;
use App\Observers\SpeciesObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Species::observe(SpeciesObserver::class);
        Post::observe(PostObserver::class);
        Author::observe(AuthorObserver::class);

        // Named limiters get their own cache-key namespace, so stacking the
        // read limit on the whole v1 group and the tighter write limit on the
        // POST subset does not collide (unnamed throttles share a signature and
        // would double-count).
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('public-writes', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
