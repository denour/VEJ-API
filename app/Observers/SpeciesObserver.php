<?php

namespace App\Observers;

use App\Jobs\GenerateModelImage;
use App\Models\Species;

class SpeciesObserver
{
    /**
     * Handle the Species "created" event.
     */
    public function created(Species $species): void
    {
        // Only generate image if one wasn't manually uploaded
        if (empty($species->image)) {
            GenerateModelImage::dispatch($species, 'image');
        }
    }

    /**
     * Handle the Species "updated" event.
     */
    public function updated(Species $species): void
    {
        //
    }

    /**
     * Handle the Species "deleted" event.
     */
    public function deleted(Species $species): void
    {
        //
    }

    /**
     * Handle the Species "restored" event.
     */
    public function restored(Species $species): void
    {
        //
    }

    /**
     * Handle the Species "force deleted" event.
     */
    public function forceDeleted(Species $species): void
    {
        //
    }
}
