<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Integration;

use Boesing\ImapToSmtpForwarder\Command\StartCommand;
use Boesing\ImapToSmtpForwarder\Configuration\ConfigurationFactory;
use Boesing\ImapToSmtpForwarder\Forwarder\ForwarderFactory;
use CuyZ\Valinor\MapperBuilder;
use Laminas\Diactoros\StreamFactory;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;

use function file_put_contents;
use function json_encode;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * Runs the `start` command against a real IMAP/SMTP server (GreenMail) and verifies the result via IMAP.
 */
final class ForwardMailsTest extends TestCase
{
    private GreenMail $greenMail;

    /** @var non-empty-string */
    private string $source;

    /** @var non-empty-list<non-empty-string> */
    private array $recipients;

    protected function setUp(): void
    {
        $this->greenMail  = new GreenMail();
        $this->source     = $this->greenMail->createUser('source');
        $this->recipients = [
            $this->greenMail->createUser('jane'),
            $this->greenMail->createUser('wade'),
        ];
    }

    public function testForwardsMessageToAllRecipientsAndDeletesOriginal(): void
    {
        $subject = uniqid('Invoice ', true);
        $this->greenMail->deliver($this->source, 'billing@example.org', $subject, 'Original body');

        $tester = $this->runForwarder(['action' => 'delete']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        foreach ($this->recipients as $recipient) {
            $messages = $this->greenMail->messages($recipient);
            self::assertCount(1, $messages);

            $message = $messages[0];
            self::assertSame('FW: ' . $subject, $message->subject());
            self::assertSame('forwarder@example.com', $message->fromEmail());
            self::assertSame($this->greenMail->email($recipient), $message->replyToEmail());
            self::assertStringContainsString('billing@example.org', $message->text());
            self::assertStringContainsString($this->greenMail->email($this->source), $message->text());

            $attachments = $message->attachments();
            self::assertCount(1, $attachments);
            self::assertStringContainsString('Subject: ' . $subject, $attachments[0]);
            self::assertStringContainsString('Original body', $attachments[0]);
        }

        self::assertSame([], $this->greenMail->messages($this->source));
    }

    public function testForwardsEveryMessageOfTheInbox(): void
    {
        $this->greenMail->deliver($this->source, 'a@example.org', 'First', 'one');
        $this->greenMail->deliver($this->source, 'b@example.org', 'Second', 'two');

        $this->runForwarder(['action' => 'delete']);

        foreach ($this->recipients as $recipient) {
            $subjects = [];
            foreach ($this->greenMail->messages($recipient) as $message) {
                $subjects[] = $message->subject();
            }

            self::assertEqualsCanonicalizing(['FW: First', 'FW: Second'], $subjects);
        }

        self::assertSame([], $this->greenMail->messages($this->source));
    }

    public function testMovesOriginalToConfiguredFolderAndMarksItAsRead(): void
    {
        $this->greenMail->createFolder($this->source, 'Archive');
        $this->greenMail->deliver($this->source, 'billing@example.org', 'Move me', 'Original body');

        $this->runForwarder(['action' => 'move', 'inboxToMove' => 'Archive']);

        self::assertCount(1, $this->greenMail->messages($this->recipients[0]));
        self::assertSame([], $this->greenMail->messages($this->source));

        $archived = $this->greenMail->messages($this->source, 'Archive');
        self::assertCount(1, $archived);
        self::assertSame('Move me', $archived[0]->subject());
        self::assertTrue($archived[0]->seen);
    }

    public function testKeepsMovedMessageUnreadWhenMarkAsReadIsDisabled(): void
    {
        $this->greenMail->createFolder($this->source, 'Archive');
        $this->greenMail->deliver($this->source, 'billing@example.org', 'Keep me unread', 'Original body');

        $this->runForwarder(['action' => 'move', 'inboxToMove' => 'Archive', 'markAsRead' => false]);

        $archived = $this->greenMail->messages($this->source, 'Archive');
        self::assertCount(1, $archived);
        self::assertFalse($archived[0]->seen);
    }

    public function testDoesNotForwardAnythingWhenInboxIsEmpty(): void
    {
        $tester = $this->runForwarder(['action' => 'delete']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        foreach ($this->recipients as $recipient) {
            self::assertSame([], $this->greenMail->messages($recipient));
        }
    }

    public function testKeepsOriginalMessageWhenSmtpServerIsUnreachable(): void
    {
        $this->greenMail->deliver($this->source, 'billing@example.org', 'Do not lose me', 'Original body');

        try {
            $this->runForwarder(['action' => 'delete'], smtpPort: 1);
            self::fail('Expected the forwarder to fail as the SMTP server is unreachable.');
        } catch (TransportException) {
            // expected
        }

        $remaining = $this->greenMail->messages($this->source);
        self::assertCount(1, $remaining);
        self::assertSame('Do not lose me', $remaining[0]->subject());
    }

    /**
     * @param array<string, mixed> $forward Overrides for the forward configuration.
     * @param int<0, 65535>        $smtpPort
     */
    private function runForwarder(array $forward, int $smtpPort = GreenMail::SMTP_PORT): CommandTester
    {
        $addresses = ['forwarder' => ['name' => 'Forwarder', 'email' => 'forwarder@example.com']];
        foreach ($this->recipients as $recipient) {
            $addresses[$recipient] = ['name' => $recipient, 'email' => $this->greenMail->email($recipient)];
        }

        $file = vfsStream::setup()->url() . '/configuration.json';
        file_put_contents($file, json_encode([
            'addresses' => $addresses,
            'templates' => [
                'default' => [
                    'Hello %recipient.name%,',
                    'a message from %message.from.email% to %message.to.email% was forwarded.',
                ],
            ],
            'imap' => [
                'main' => [
                    'hostname' => GreenMail::HOST,
                    'port' => GreenMail::IMAPS_PORT,
                    'username' => $this->source,
                    'password' => GreenMail::PASSWORD,
                ],
            ],
            'smtp' => [
                'main' => [
                    'hostname' => GreenMail::HOST,
                    'port' => $smtpPort,
                    'username' => $this->source,
                    'password' => GreenMail::PASSWORD,
                ],
            ],
            'forwards' => [
                $forward + [
                    'imap' => 'main',
                    'smtp' => ['identifier' => 'main', 'sender' => 'forwarder'],
                    'recipients' => $this->recipients,
                    'template' => 'default',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $configuration = (new ConfigurationFactory(new StreamFactory(), (new MapperBuilder())->mapper()))
            ->createFromConfigurationFile($file);

        $tester = new CommandTester(new StartCommand($configuration, new ForwarderFactory(new StreamFactory())));
        $tester->execute([]);

        return $tester;
    }
}
