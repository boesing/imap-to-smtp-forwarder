<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Integration;

use IMAP\Connection;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

use function file_get_contents;
use function imap_close;
use function imap_fetch_overview;
use function imap_fetchbody;
use function imap_last_error;
use function imap_open;
use function imap_search;
use function microtime;
use function sprintf;
use function str_contains;
use function stream_context_create;
use function usleep;

use const FT_PEEK;

/**
 * Thin client for the GreenMail container defined in compose.yml.
 *
 * The accounts are created by GreenMail itself (see `greenmail.users` in compose.yml).
 */
final class GreenMail
{
    public const HOST       = 'greenmail';
    public const SMTP_PORT  = 3025;
    public const IMAPS_PORT = 3993;
    public const PASSWORD   = 'secret';

    /** Maximum time in seconds to wait for the forwarder (polling every second) to process a single mail. */
    private const TIMEOUT = 5;

    private const API    = 'http://greenmail:8080';
    private const DOMAIN = 'example.com';

    /**
     * @param non-empty-string $login
     * @return non-empty-string
     */
    public function email(string $login): string
    {
        return sprintf('%s@%s', $login, self::DOMAIN);
    }

    /**
     * Delivers a mail to the INBOX of the given user.
     *
     * @param non-empty-string $login
     * @param non-empty-string $fromEmail
     */
    public function deliver(string $login, string $fromEmail, string $subject, string $body): void
    {
        $mailer = new Mailer(Transport::fromDsn(sprintf('smtp://%s:%d', self::HOST, self::SMTP_PORT)));
        $mailer->send(
            (new Email())
                ->from($fromEmail)
                ->to($this->email($login))
                ->subject($subject)
                ->text($body),
        );
    }

    /**
     * Removes all mails from all mailboxes. The folders itself are kept.
     */
    public function purge(): void
    {
        $response = file_get_contents(self::API . '/api/mail/purge', false, stream_context_create([
            'http' => ['method' => 'POST', 'content' => ''],
        ]));

        if ($response === false || ! str_contains($response, 'Purged mails')) {
            throw new RuntimeException(sprintf('Unable to purge GreenMail mails: %s', $response === false ? 'no response' : $response));
        }
    }

    /**
     * Waits until a mail with the given subject is available in the folder.
     *
     * @param non-empty-string $login
     * @param non-empty-string $folder
     */
    public function awaitMessage(string $login, string $subject, string $folder = 'INBOX'): ReceivedMessage
    {
        $deadline = microtime(true) + self::TIMEOUT;

        do {
            foreach ($this->messages($login, $folder) as $message) {
                if ($message->subject() === $subject) {
                    return $message;
                }
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        Assert::fail(sprintf(
            'Mail with subject "%s" did not arrive in "%s" of "%s" within %d seconds.',
            $subject,
            $folder,
            $login,
            self::TIMEOUT,
        ));
    }

    /**
     * Waits until no mail with the given subject is left in the folder.
     *
     * @param non-empty-string $login
     * @param non-empty-string $folder
     */
    public function awaitNoMessage(string $login, string $subject, string $folder = 'INBOX'): void
    {
        $deadline = microtime(true) + self::TIMEOUT;

        do {
            $found = false;
            foreach ($this->messages($login, $folder) as $message) {
                if ($message->subject() === $subject) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        Assert::fail(sprintf(
            'Mail with subject "%s" is still in "%s" of "%s" after %d seconds.',
            $subject,
            $folder,
            $login,
            self::TIMEOUT,
        ));
    }

    /**
     * Returns all messages of the folder which are not flagged as deleted.
     *
     * @param non-empty-string $login
     * @param non-empty-string $folder
     * @return list<ReceivedMessage>
     */
    private function messages(string $login, string $folder): array
    {
        $connection = $this->connect($login, $folder);

        $messages = [];
        foreach (imap_search($connection, 'UNDELETED') ?: [] as $number) {
            $raw = imap_fetchbody($connection, $number, '', FT_PEEK);
            if ($raw === false) {
                throw new RuntimeException(sprintf('Unable to fetch message %d.', $number));
            }

            $overview   = imap_fetch_overview($connection, (string) $number);
            $messages[] = new ReceivedMessage($raw, ($overview[0]->seen ?? 0) === 1);
        }

        imap_close($connection);

        return $messages;
    }

    private function connect(string $login, string $folder): Connection
    {
        $connection = imap_open(
            sprintf('{%s:%d/service=imap/ssl}%s', self::HOST, self::IMAPS_PORT, $folder),
            $login,
            self::PASSWORD,
            0,
            1,
        );
        if ($connection === false) {
            throw new RuntimeException(sprintf('Unable to connect to GreenMail IMAP: %s', (string) imap_last_error()));
        }

        return $connection;
    }
}
