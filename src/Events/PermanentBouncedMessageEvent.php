<?php

namespace gustavoh3nryk\MailTracker\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use gustavoh3nryk\MailTracker\Model\SentEmail;

class PermanentBouncedMessageEvent implements ShouldQueue
{
    use SerializesModels;

    public $email_address;
    public $sent_email;

    /**
     * Create a new event instance.
     *
     * @param  email_address  $email_address
     * @param  sent_email  $sent_email
     * @return void
     */
    public function __construct($email_address, SentEmail $sent_email = null)
    {
        $this->email_address = $email_address;
        $this->sent_email = $sent_email;
    }
}
