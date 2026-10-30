<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\OrgCourse;
use App\Models\OrgCompany;

class CourseEnrollmentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $course;
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct($user, OrgCourse $course, OrgCompany $company)
    {
        $this->user = $user;
        $this->course = $course;
        $this->company = $company;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎓 ¡Bienvenido al curso: ' . $this->course->title . '!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.academy.course_enrollment',
        );
    }
}