<?php

namespace App\Observers;

use App\Models\PKWT;
use App\Services\RekapService;

class PkwtObserver
{
    /**
     * Handle the PKWT "created" event.
     */
    public function created(PKWT $pkwt): void
    {
        RekapService::updateRekapBulanBerjalan();
    }

    /**
     * Handle the PKWT "updated" event.
     */
    public function updated(PKWT $pkwt): void
    {
        if ($pkwt->isDirty('TKK')) {
            RekapService::updateRekapBulanBerjalan();
        }
    }

    /**
     * Handle the PKWT "deleted" event.
     */
    public function deleted(PKWT $pkwt): void
    {
        RekapService::updateRekapBulanBerjalan();
    }

    /**
     * Handle the PKWT "restored" event.
     */
    public function restored(PKWT $pkwt): void
    {
        //
    }

    /**
     * Handle the PKWT "force deleted" event.
     */
    public function forceDeleted(PKWT $pkwt): void
    {
        //
    }
}
