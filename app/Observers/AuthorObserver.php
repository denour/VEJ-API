<?php

namespace App\Observers;

use App\Jobs\GenerateModelImage;
use App\Models\Author;

class AuthorObserver
{
    /**
     * Handle the Author "created" event.
     */
    public function created(Author $author): void
    {
        // Only generate an avatar if one wasn't manually uploaded.
        if (empty($author->avatar_url)) {
            GenerateModelImage::dispatch($author, 'avatar_url');
        }
    }

    /**
     * Handle the Author "updated" event.
     */
    public function updated(Author $author): void
    {
        //
    }

    /**
     * Handle the Author "deleted" event.
     */
    public function deleted(Author $author): void
    {
        //
    }

    /**
     * Handle the Author "restored" event.
     */
    public function restored(Author $author): void
    {
        //
    }

    /**
     * Handle the Author "force deleted" event.
     */
    public function forceDeleted(Author $author): void
    {
        //
    }
}
