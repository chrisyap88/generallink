<?php

namespace App\Mail;

use App\Models\Agent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgentVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Agent $agent;
    public string $verificationUrl;

    public function __construct(Agent $agent, string $verificationUrl)
    {
        $this->agent           = $agent;
        $this->verificationUrl = $verificationUrl;
    }

    public function build()
    {
        return $this->subject('Verify Your GeneralLink Account')
                    ->view('emails.agent-verification')
                    ->with([
                        'agent'           => $this->agent,
                        'verificationUrl' => $this->verificationUrl,
                    ]);
    }
}
