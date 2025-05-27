<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotifyUser;
use App\Models\User;

class SendDailyNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    public function handle(): void
    {
        $user = User::find($this->user_id);

        if ($user) {
            Mail::to($user->email)->send(new NotifyUser($user));
        } else {
            \Log::error("User with ID {$this->user_id} not found.");
        }
    }
}