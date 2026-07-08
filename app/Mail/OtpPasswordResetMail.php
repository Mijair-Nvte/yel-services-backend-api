<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpPasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $otp,
        public string $userName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código para restablecer tu contraseña',
        );
    }

    public function content(): Content
    {
        return new Content(
            // Asegúrate de crear esta vista en resources/views/emails/auth/
            view: 'emails.auth.password_reset_otp',
        );
    }
}
