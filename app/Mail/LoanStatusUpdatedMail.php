<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\OrgLoanApplication;
use App\Models\OrgCompany;

class LoanStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $partner;
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct(OrgLoanApplication $application, OrgCompany $company)
    {
        $this->application = $application;
        $this->company = $company;
        $this->partner = $application->user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔄 Actualización de Estatus: Solicitud de Préstamo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loans.status_updated',
        );
    }
}