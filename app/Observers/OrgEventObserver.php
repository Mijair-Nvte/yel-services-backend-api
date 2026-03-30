<?php

namespace App\Observers;

use App\Events\EventCreated;
use App\Models\OrgEvent;

class OrgEventObserver
{
    public function created(OrgEvent $event): void
    {
        event(new EventCreated($event));
    }
}
