<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserLoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $userName;
    public int $expiresInMinutes;

    public function __construct(string $otp, string $userName)
    {
        $this->otp = $otp;
        $this->userName = $userName;
        $this->expiresInMinutes = 5;
    }

    public function build(): self
    {
        return $this->from(
                config('mail.from.address'),
                config('mail.from.name')
            )
            ->subject('Your Astrotring Login OTP')
            ->view('emails.user-login-otp')
            ->text('emails.user-login-otp-text');
    }
}
