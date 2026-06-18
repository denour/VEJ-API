<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\Post;
use App\Models\Species;
use App\Observers\AuthorObserver;
use App\Observers\PostObserver;
use App\Observers\SpeciesObserver;
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
    }
}
