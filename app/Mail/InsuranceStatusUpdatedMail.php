<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\OrgInsuranceApplication; 
use App\Models\OrgCompany;

class InsuranceStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $partner;
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct(OrgInsuranceApplication $application, OrgCompany $company) 
    {
        $this->application = $application;
        $this->company = $company;
        $this->partner = $application->user; // El partner dueño de la solicitud
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔄 Actualización de Estatus: Solicitud de Seguros',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.insurance.status_updated',
        );
    }
}