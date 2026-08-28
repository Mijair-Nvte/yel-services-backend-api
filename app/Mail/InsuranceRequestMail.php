<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\OrgInsuranceApplication;
use App\Models\OrgCompany; // 👈 1. Importar

class InsuranceRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $user;
    public $company; // 👈 2. Agregar propiedad
    public $partner; // 👈 3. Agregar propiedad

    public function __construct(OrgInsuranceApplication $application, $user, OrgCompany $company)
    {
        $this->application = $application;
        $this->user = $user;
        $this->company = $company; // 👈 Asignar
        $this->partner = $application->user; // 👈 Asignar
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔔 Nuevo Prospecto de Seguro Registrado',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.insurance.new_request',
        );
    }
}