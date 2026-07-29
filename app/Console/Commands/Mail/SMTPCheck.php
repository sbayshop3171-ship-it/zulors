<?php

namespace App\Console\Commands\Mail;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Console\Traits\ValidatesInput;

class SMTPCheck extends Command
{
    use ValidatesInput;
    
    protected $signature = 'mail:check';

    private $emailAddress = '';

    protected $description = 'Check if Mailing is working';

    public function handle()
    {
        $this->emailAddress = $this->askValid('Enter email:', [
            'string',
            'email'
        ]);

        $this->sendEmail();
    }

    private function sendEmail()
    {
        try {
            Mail::raw(__('email.testing.body', ['app_name' => config('app.name')]), function ($message) {
                $message->to($this->emailAddress)->subject(__('email.testing.subject', ['app_name' => config('app.name')]));
            });

            $this->info('E-Mail sent!');
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
    }
}
