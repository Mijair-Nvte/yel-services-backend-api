<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareDocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $documentTitle;
    public $documentUrl;
    public $senderName;
    public $customMessage;

    public function __construct(string $documentTitle, string $documentUrl, string $senderName, ?string $customMessage = null)
    {
        $this->documentTitle = $documentTitle;
        $this->documentUrl = $documentUrl;
        $this->senderName = $senderName;
        $this->customMessage = $customMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->senderName} te ha compartido un documento",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.documents.shared',
        );
    }
}