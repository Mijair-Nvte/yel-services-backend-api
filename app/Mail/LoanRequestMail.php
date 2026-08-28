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
class LoanRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $user;
public $company;
public $partner;
    /**
     * Create a new message instance.
     */
public function __construct(OrgLoanApplication $application, $user, OrgCompany $company)
    {
        $this->application = $application;
        $this->user = $user;
        $this->company = $company;
        $this->partner = $application->user;
    }
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔔 Nuevo Prospecto de Préstamo Registrado',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.loans.new_request',
        );
    }
}