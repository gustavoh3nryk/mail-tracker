<?php

namespace gustavoh3nryk\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use gustavoh3nryk\MailTracker\Model\SentEmail;
use gustavoh3nryk\MailTracker\RecordBounceJob;
use gustavoh3nryk\MailTracker\RecordDeliveryJob;
use gustavoh3nryk\MailTracker\RecordTrackingJob;
use gustavoh3nryk\MailTracker\RecordComplaintJob;
use gustavoh3nryk\MailTracker\RecordLinkClickJob;
use gustavoh3nryk\MailTracker\Events\ViewEmailEvent;
use gustavoh3nryk\MailTracker\Events\LinkClickedEvent;

class RecordTrackingJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_records_views()
    {
        Event::fake();
        $track = \gustavoh3nryk\MailTracker\Model\SentEmail::create([
            'hash' => Str::random(32),
        ]);
        $job = new RecordTrackingJob($track, '127.0.0.1');

        $job->handle();

        Event::assertDispatched(ViewEmailEvent::class, function ($e) use ($track) {
            return $track->id == $e->sent_email->id &&
                $e->ip_address == '127.0.0.1';
        });
        $this->assertDatabaseHas('sent_emails', [
            'id' => $track->id,
            'opens' => 1,
        ]);
    }
}
