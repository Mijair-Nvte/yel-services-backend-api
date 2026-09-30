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

class LoanAssignedAgentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $agent; // El vendedor
    public $company;
    public $partner; // Quien lo registró

    public function __construct(OrgLoanApplication $application, $agent, OrgCompany $company)
    {
        $this->application = $application;
        $this->agent = $agent; 
        $this->company = $company;
        $this->partner = $application->user; 
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎯 Te han asignado un nuevo prospecto de préstamo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loans.assigned_to_agent',
        );
    }
}