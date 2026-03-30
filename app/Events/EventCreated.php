<?php

namespace App\Events;

use App\Models\OrgEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventCreated
{
    use Dispatchable, SerializesModels;

    public OrgEvent $event;

    public function __construct(OrgEvent $event)
    {
        $this->event = $event;
    }
}
