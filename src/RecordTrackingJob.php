<?php

namespace gustavoh3nryk\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use gustavoh3nryk\MailTracker\Model\SentEmail;
use gustavoh3nryk\MailTracker\Events\ViewEmailEvent;
use gustavoh3nryk\MailTracker\Events\LinkClickedEvent;
use gustavoh3nryk\MailTracker\Model\SentEmailUrlClicked;
use gustavoh3nryk\MailTracker\Events\EmailDeliveredEvent;

class RecordTrackingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $sentEmail;
    public $ipAddress;

    public function __construct($sentEmail, $ipAddress)
    {
        $this->sentEmail = $sentEmail;
        $this->ipAddress = $ipAddress;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        $this->sentEmail->opens++;
        $this->sentEmail->save();
        Event::dispatch(new ViewEmailEvent($this->sentEmail, $this->ipAddress));
    }
}
