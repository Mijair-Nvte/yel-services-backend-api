<?php

namespace App\Mail;

use App\Models\OrgCompanyNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrgCompanyNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrgCompanyNotice $notice
    ) {}

    public function build()
    {
        return $this
            ->subject('📢 Nuevo aviso: ' . $this->notice->title)
            ->view('emails.org-company-notice');
    }
}
