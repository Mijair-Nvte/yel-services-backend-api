<?php

namespace App\Mail;

use App\Models\OrgCompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrgCompanyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrgCompanyInvitation $invite
    ) {}

    public function build()
    {
        $url = config('app.frontend_url')
            . "/invite?token=" . $this->invite->token;

        return $this->subject("Invitación a un workspace")
            ->view('emails.org-company-invite')
            ->with([
                'company' => $this->invite->company,
                'url' => $url,
            ]);
    }
}
