<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $email;
    public $content;
    public $subject;
    public $url;

    public function __construct($email, $content, $subject, $url)
    {
        $this->email = $email;
        $this->content = $content;
        $this->subject = $subject;
        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.email_verification')->subject($this->subject)->with([
            // 'name' => $this->user->name == null ? '' : $this->user->name,
            'content' => $this->content,
            'url' => isset($this->url) && $this->url ? $this->url : 'home'
        ]);
    }
}
