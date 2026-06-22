<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\OrgInsuranceApplication;

class InsuranceRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(OrgInsuranceApplication $application, $user)
    {
        $this->application = $application;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔔 Nuevo Prospecto de Seguro Registrado',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.insurance.new_request',
        );
    }
}