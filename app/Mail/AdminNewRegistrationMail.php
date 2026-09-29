<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNewRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $details;

    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function build()
    {
        $status = $this->details['status'];
        return $this->subject("GeneralLink New Registration — {$status}")
                    ->view('emails.admin-new-registration')
                    ->with($this->details);
    }
}
