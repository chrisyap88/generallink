<?php

namespace App\Mail;

use App\Models\Agent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgentAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Agent $agent;
    public Agent $gl;

    public function __construct(Agent $agent, Agent $gl)
    {
        $this->agent = $agent;
        $this->gl    = $gl;
    }

    public function build()
    {
        return $this->subject('Your GeneralLink Account Has Been Assigned!')
                    ->view('emails.agent-assigned')
                    ->with([
                        'agent' => $this->agent,
                        'gl'    => $this->gl,
                    ]);
    }
}
