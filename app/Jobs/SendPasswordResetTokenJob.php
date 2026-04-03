<?php

namespace App\Jobs;

use App\Mail\PasswordResetTokenEmail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetTokenJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $user,
        protected string $token
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new PasswordResetTokenEmail($this->token));
    }
}
