<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $firstName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your SkillLink verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: [
                'code' => $this->code,
                'firstName' => $this->firstName,
            ],
        );
    }
}
