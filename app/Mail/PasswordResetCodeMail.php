<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $firstName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your SkillLink password reset code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
            with: [
                'code' => $this->code,
                'firstName' => $this->firstName,
            ],
        );
    }
}
