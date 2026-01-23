<?php

namespace App\Observers;

use App\Models\Magang;
use App\Services\RekapService;

class MagangObserver
{
    /**
     * Handle the Magang "created" event.
     */
    public function created(Magang $magang): void
    {
        RekapService::updateRekapBulanBerjalan();
    }

    /**
     * Handle the Magang "updated" event.
     */
    public function updated(Magang $magang): void
    {
        if ($magang->isDirty('TKK')) {
            RekapService::updateRekapBulanBerjalan();
        }
    }

    /**
     * Handle the Magang "deleted" event.
     */
    public function deleted(Magang $magang): void
    {
        RekapService::updateRekapBulanBerjalan();
    }

    /**
     * Handle the Magang "restored" event.
     */
    public function restored(Magang $magang): void
    {
        //
    }

    /**
     * Handle the Magang "force deleted" event.
     */
    public function forceDeleted(Magang $magang): void
    {
        //
    }
}
