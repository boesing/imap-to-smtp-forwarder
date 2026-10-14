<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Integration;

use IMAP\Connection;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

use function bin2hex;
use function file_get_contents;
use function imap_close;
use function imap_createmailbox;
use function imap_fetch_overview;
use function imap_fetchbody;
use function imap_last_error;
use function imap_open;
use function imap_search;
use function is_array;
use function json_decode;
use function json_encode;
use function random_bytes;
use function sprintf;
use function stream_context_create;

use const FT_PEEK;
use const JSON_THROW_ON_ERROR;

/**
 * Thin client for the GreenMail container defined in compose.yml.
 */
final class GreenMail
{
    public const HOST       = 'greenmail';
    public const SMTP_PORT  = 3025;
    public const IMAPS_PORT = 3993;
    public const PASSWORD   = 'secret';

    private const API    = 'http://greenmail:8080';
    private const DOMAIN = 'example.com';

    /**
     * Creates a user with a unique login, so that tests never see each others mails.
     *
     * @param non-empty-string $prefix
     * @return non-empty-string
     */
    public function createUser(string $prefix): string
    {
        $login = sprintf('%s-%s', $prefix, bin2hex(random_bytes(4)));

        $response = file_get_contents(self::API . '/api/user', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode([
                    'login' => $login,
                    'email' => $this->email($login),
                    'password' => self::PASSWORD,
                ], JSON_THROW_ON_ERROR),
            ],
        ]));

        $user = $response === false ? null : json_decode($response, true);
        if (! is_array($user) || ($user['login'] ?? null) !== $login) {
            throw new RuntimeException(sprintf('Unable to create GreenMail user "%s".', $login));
        }

        return $login;
    }

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
     * @param non-empty-string $login
     * @param non-empty-string $folder
     */
    public function createFolder(string $login, string $folder): void
    {
        $connection = $this->connect($login);

        if (! imap_createmailbox($connection, $this->path($folder))) {
            throw new RuntimeException(sprintf('Unable to create folder "%s": %s', $folder, (string) imap_last_error()));
        }

        imap_close($connection);
    }

    /**
     * Returns all messages of the folder which are not flagged as deleted.
     *
     * @param non-empty-string $login
     * @param non-empty-string $folder
     * @return list<ReceivedMessage>
     */
    public function messages(string $login, string $folder = 'INBOX'): array
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

    private function connect(string $login, string $folder = 'INBOX'): Connection
    {
        $connection = imap_open($this->path($folder), $login, self::PASSWORD, 0, 1);
        if ($connection === false) {
            throw new RuntimeException(sprintf('Unable to connect to GreenMail IMAP: %s', (string) imap_last_error()));
        }

        return $connection;
    }

    private function path(string $folder): string
    {
        return sprintf('{%s:%d/service=imap/ssl}%s', self::HOST, self::IMAPS_PORT, $folder);
    }
}
