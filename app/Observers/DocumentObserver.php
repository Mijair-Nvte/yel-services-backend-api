<?php

namespace App\Observers;

use App\Events\DocumentUploaded;
use App\Models\Document;

class DocumentObserver
{
    public function created(Document $document): void
    {
        event(new DocumentUploaded($document));
    }
}
