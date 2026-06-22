<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginSuccessfulMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $time;

    public function __construct(
        public string $userName,
        public ?string $ip,
        public ?string $userAgent
    ) {
        $this->time = now()->timezone('America/Mexico_City')->format('d/m/Y H:i A');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerta de seguridad: Nuevo inicio de sesión detectado',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.login_success',
        );
    }
}