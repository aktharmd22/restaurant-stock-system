<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| On Hostinger this runs from a single cron entry:
|
|   * * * * * cd ~/domains/SITE/app && php artisan schedule:run
|
| Nothing here is on the critical path. The ask -> approve -> send -> receive
| loop all happens inside the web request, so even a fifteen-minute cron
| cannot slow down the two people who use this app.
|
*/

// A request nobody has opened in half an hour gets a message to a phone.
Schedule::command('requests:escalate')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// The ledger is the truth. This checks the cached balances still agree with it
// and repairs them if they do not.
Schedule::command('stock:rebuild-balances')
    ->dailyAt('03:30')
    ->withoutOverlapping();
