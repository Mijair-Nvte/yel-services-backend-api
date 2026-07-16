<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareServiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $serviceName;
    public $serviceUrl;
    public $senderName;
    public $customMessage;

    public function __construct(string $serviceName, string $serviceUrl, string $senderName, ?string $customMessage = null)
    {
        $this->serviceName = $serviceName;
        $this->serviceUrl = $serviceUrl;
        $this->senderName = $senderName;
        $this->customMessage = $customMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->senderName} te ha recomendado un servicio",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.services.shared',
        );
    }
}