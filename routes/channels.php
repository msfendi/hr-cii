<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel untuk HR Admin notifications
Broadcast::channel('hr.contract-notifications', function ($user) {
    if ($user && $user->hasAnyRole(['Admin', 'HRD', 'Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING'])) {
        return true;
    }
    
    return false;
});