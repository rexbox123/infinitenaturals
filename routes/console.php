<?php

use Illuminate\Support\Facades\Schedule;

// Release stock held by abandoned card checkouts (after confirming with Stripe).
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();

// Housekeeping.
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('model:prune')->daily();
