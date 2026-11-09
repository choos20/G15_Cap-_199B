<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $userData;
    public $plainPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(array $userData, string $plainPassword = '')
    {
        $this->userData = $userData;
        $this->plainPassword = $plainPassword;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your ProjectLink account has been created')
                    ->view('emails.account_created')
                    ->with([
                        'userData' => $this->userData,
                        'plainPassword' => $this->plainPassword,
                    ]);
    }
}
