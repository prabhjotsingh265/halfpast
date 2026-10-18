<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Drops recipients on domains reserved for examples and testing, which can
 * never receive mail, and the message entirely if nobody is left. Keeps a
 * real mail provider from bouncing mail meant for the demo customers.
 */
class SkipUndeliverableAddresses
{
    private const RESERVED = ['example.com', 'example.net', 'example.org', 'localhost'];

    private const RESERVED_ENDINGS = ['.test', '.example', '.invalid', '.localhost'];

    public function handle(MessageSending $event): bool
    {
        if (! config('mail.skip_reserved_domains')) {
            return true;
        }

        $message = $event->message;
        $keep = fn (array $addresses) => array_values(array_filter($addresses, fn (Address $address) => ! self::reserved($address->getAddress())));

        $to = $keep($message->getTo());
        $cc = $keep($message->getCc());
        $bcc = $keep($message->getBcc());

        if ($to === [] && $cc === [] && $bcc === []) {
            return false;
        }

        foreach (['To' => $to, 'Cc' => $cc, 'Bcc' => $bcc] as $header => $addresses) {
            $message->getHeaders()->remove($header);

            if ($addresses !== []) {
                $message->getHeaders()->addMailboxListHeader($header, $addresses);
            }
        }

        return true;
    }

    public static function reserved(string $email): bool
    {
        $domain = mb_strtolower((string) substr((string) strrchr($email, '@'), 1));

        if (in_array($domain, self::RESERVED, true)) {
            return true;
        }

        foreach (self::RESERVED_ENDINGS as $ending) {
            if (str_ends_with($domain, $ending)) {
                return true;
            }
        }

        return false;
    }
}
