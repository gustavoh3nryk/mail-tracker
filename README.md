# MailTracker

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

MailTracker will hook into all outgoing emails from Laravel and inject a tracking code into it. It will also store the rendered email in the database. There is also an interface to view sent emails.

## NOTE: If you are using Laravel 9.x you MUST be on version 9.6.0 or higher.

## Install

Via Composer

```bash
composer require gustavoh3nryk/mail-tracker
```

Publish the config file and migration

```bash
php artisan vendor:publish --provider="gustavoh3nryk\MailTracker\MailTrackerServiceProvider"
```

Run the migration

```bash
php artisan migrate
```

Note: If you would like to use a different connection to store your models,
you should update the mail-tracker.php config entry `connection` before running the migrations.

If you would like to use your own migrations, you can skip this library migrations by calling `MailTracker::ignoreMigrations()`. For example:

```php
// In AppServiceProvider

public function boot()
{
    MailTracker::ignoreMigrations();
}
```

## Usage

Once installed, all outgoing mail will be logged to the database. The following config options are available in config/mail-tracker.php:

-   **name**: set your App Name.
-   **inject-pixel**: set to true to inject a tracking pixel into all outgoing html emails.
-   **track-links**: set to true to rewrite all anchor href links to include a tracking link. The link will take the user back to your website which will then redirect them to the final destination after logging the click.
-   **expire-days**: How long in days that an email should be retained in your database. If you are sending a lot of mail, you probably want it to eventually expire. Set it to zero to never purge old emails from the database.
-   **route**: The route information for the tracking URLs. Set the prefix and middlware as desired.
-   **admin-route**: The route information for the admin. Set the prefix and middleware.
-   **admin-template**: The params for the Admin Panel and Views. You can integrate your existing Admin Panel with the MailTracker admin panel.
-   **date-format**: You can define the format to show dates in the Admin Panel.
-   **content-max-size**: You can overwrite default maximum length limit for `content` database field. Do not forget update it's type from `text` if you need to make it longer.

If you do not wish to have an email tracked, then you can add the `X-No-Track` header to your message. Put any random string into this header to prevent the tracking from occurring. The header will be removed from the email prior to being sent.

```php
\Mail::send('email.test', [], function ($message) {
    // ... other settings here
    $message->getHeaders()->addTextHeader('X-No-Track',Str::random(10));
});
```

## Note on dev testing

Several people have reported the tracking pixel not working while they were testing. What is happening with the tracking pixel is that the email client is connecting to your website to log the view. In order for this to happen, images have to be visible in the client, and the client has to be able to connect to your server.

When you are in a dev environment (i.e. using the `.test` domain with Valet, or another domain known only to your computer) you must have an email client on your computer. Further complicating this is the fact that Gmail and some other web-based email clients don't connect to the images directly, but instead connect via proxy. That proxy won't have a connection to your `.test` domain and therefore will not properly track emails. I always recommend using [mailtrap.io](https://mailtrap.io) for any development environment when you are sending emails. Not only does this solve the issue (mailtrap.io does not use a proxy service to forward images in the emails) but it also protects you from accidentally sending real emails from your test environment.

## Events

When an email is sent, viewed, or a link is clicked, its tracking information is counted in the database using the gustavoh3nryk\MailTracker\Model\SentEmail model. This processing is done via dispatched jobs to the queue in order to prevent the database from being overwhelmed in an email blast situation. You may choose the queue that these events are dispatched via the `mail-tracker.tracker-queue` config setting, or leave it `null` to use the default queue. By using a non-default queue, you can prioritize application-critical tasks above these tracking tasks.

You may want to do additional processing on these events, so an event is fired in these cases:

-   gustavoh3nryk\MailTracker\Events\EmailSentEvent
    -   Public attribute `sent_email` contains the `SentEmail` model
-   gustavoh3nryk\MailTracker\Events\ViewEmailEvent
    -   Public attribute `sent_email` contains the `SentEmail` model
    -   Public attribute `ip_address` contains the IP address that was used to trigger the event
-   gustavoh3nryk\MailTracker\Events\LinkClickedEvent
    -   Public attribute `sent_email` contains the `SentEmail` model
    -   Public attribute `ip_address` contains the IP address that was used to trigger the event
    -   Public attribute `link_url` contains the clicked URL

If you are using the Amazon SNS notification system, these events are fired so you can do additional processing.

-   gustavoh3nryk\MailTracker\Events\EmailDeliveredEvent (when you received a "message delivered" event, you may want to mark the email as "good" or "delivered" in your database)
    -   Public attribute `sent_email` contains the `SentEmail` model
    -   Public attribute `email_address` contains the specific address that was used to trigger the event
-   gustavoh3nryk\MailTracker\Events\ComplaintMessageEvent (when you received a complaint, ex: marked as "spam", you may want to remove the email from your database)
    -   Public attribute `sent_email` contains the `SentEmail` model
    -   Public attribute `email_address` contains the specific address that was used to trigger the event
-   gustavoh3nryk\MailTracker\Events\PermanentBouncedMessageEvent (when you receive a permanent bounce, you may want to mark the email as bad or remove it from your database)
    gustavoh3nryk\MailTracker\Events\TransientBouncedMessageEvent (when you receive a transient bounce. Check the event's public attributes for `bounce_sub_type` and `diagnostic_code` to determine if you want to do additional processing when this event is received.)
    -   Public attribute `sent_email` contains the `SentEmail` model
    -   Public attribute `email_address` contains the specific address that was used to trigger the event

To install an event listener, you will want to create a file like the following:

```php
<?php

namespace App\Listeners;

use gustavoh3nryk\MailTracker\Events\ViewEmailEvent;

class EmailViewed
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ViewEmailEvent  $event
     * @return void
     */
    public function handle(ViewEmailEvent $event)
    {
        // Access the model using $event->sent_email
        // Access the IP address that triggered the event using $event->ip_address
    }
}
```

```php
<?php

namespace App\Listeners;

use gustavoh3nryk\MailTracker\Events\PermanentBouncedMessageEvent;

class BouncedEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PermanentBouncedMessageEvent  $event
     * @return void
     */
    public function handle(PermanentBouncedMessageEvent $event)
    {
        // Access the email address using $event->email_address
    }
}
```

Then you must register the events you want to act on in your \App\Providers\EventServiceProvider \$listen array:

```php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    'gustavoh3nryk\MailTracker\Events\EmailSentEvent' => [
        'App\Listeners\EmailSent',
    ],
    'gustavoh3nryk\MailTracker\Events\ViewEmailEvent' => [
        'App\Listeners\EmailViewed',
    ],
    'gustavoh3nryk\MailTracker\Events\LinkClickedEvent' => [
        'App\Listeners\EmailLinkClicked',
    ],
    'gustavoh3nryk\MailTracker\Events\EmailDeliveredEvent' => [
        'App\Listeners\EmailDelivered',
    ],
    'gustavoh3nryk\MailTracker\Events\ComplaintMessageEvent' => [
        'App\Listeners\EmailComplaint',
    ],
    'gustavoh3nryk\MailTracker\Events\PermanentBouncedMessageEvent' => [
        'App\Listeners\BouncedEmail',
    ],
];
```

### Passing data to the event listeners

Often times you may need to link a sent email to another model. The best way to handle this is to add a header to your outgoing email that you can retrieve in your event listener. Here is an example:

```php
/**
 * Send an email and do processing on a model with the email
 */
\Mail::send('email.test', [], function ($message) use($email, $subject, $name, $model) {
    $message->from('from@johndoe.com', 'From Name');
    $message->sender('sender@johndoe.com', 'Sender Name');
    $message->to($email, $name);
    $message->subject($subject);

    // Create a custom header that we can later retrieve
    $message->getHeaders()->addTextHeader('X-Model-ID',$model->id);
});
```

and then in your event listener:

```
public function handle(EmailSentEvent $event)
{
    $tracker = $event->sent_email;
    $model_id = $event->sent_email->getHeader('X-Model-ID');
    $model = Model::find($model_id);
    // Perform your tracking/linking tasks on $model knowing the SentEmail object
}
```

Note that the headers you are attaching to the email are actually going out with the message, so do not store any data that you wouldn't want to expose to your email recipients.

## Exceptions

The following exceptions may be thrown. You may add them to your ignore list in your exception handler, or handle them as you wish.

-   gustavoh3nryk\MailTracker\Exceptions\BadUrlLink - Something went wrong with the url link. Basically, the system could not properly parse the URL link to send the redirect to.

## Amazon SES features

If you use Amazon SES, you can add some additional information to your tracking. To set up the SES callbacks, first set up SES notifications under your domain in the SES control panel. Then subscribe to the topic by going to the admin panel of the notification topic and creating a subscription for the URL you copied from the admin page. The system should immediately respond to the subscription request. If you like, you can use multiple subscriptions (i.e. one for delivery, one for bounces). See above for events that are fired on a failed message. **For added security, it is recommended to set the topic ARN into the mail-tracker config.**

## Views

When you run the `php artisan vendor:publish` command, simple views will add to your resources/views/vendor/emailTrakingViews that you can customize. You of course my build your entire admin pages from scratch using these views as a guide.

## Admin Panel

MailTracker comes with a built-in administration area. The default configuration that is published with the package puts it behind the `can:see-sent-emails` middleware; you may create a gate for this rule or change it to use one of your own. You may also change the default prefix as well as disable the admin routes completely.

The route name is 'mailTracker_Index'. The standard admin panel route is located at /email-manager. You can use route names to include them into your existing admin menu. You can customize your route in the config file. You can see all sent emails, total opens, total urls clicks, show individuals emails and show the urls clicked details.

All views (email tamplates, panel) can be customized in resources/views/vendor/emailTrakingViews.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email gustavo.h3nryk@gmail.com instead of using the issue tracker.

## Credits

-   [J David Baker][link-author-old]
-   [Gustavo Henrique][link-author]
-   [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/gustavoh3nryk/mail-tracker.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/gustavoh3nryk/mail-tracker.svg?style=flat-square
[link-packagist]: https://packagist.org/packages/gustavoh3nryk/mail-tracker
[link-downloads]: https://packagist.org/packages/gustavoh3nryk/mail-tracker
[link-author-old]: https://github.com/jdavidbakr
[link-author]: https://github.com/gustavoh3nryk
[link-contributors]: ../../contributors
