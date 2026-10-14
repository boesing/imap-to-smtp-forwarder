<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function ucfirst;
use function uniqid;

/**
 * Delivers mails to GreenMail and verifies what the forwarder (production image, started with
 * tests/Integration/configuration.json via compose.yml) does with them. All tests share the running forwarder, so
 * every mail is identified by a unique subject.
 */
final class ForwardMailsTest extends TestCase
{
    private const DELETE_SOURCE = 'fwd-delete';
    private const MOVE_SOURCE   = 'fwd-move';
    private const UNREAD_SOURCE = 'fwd-unread';

    private GreenMail $greenMail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->greenMail = new GreenMail();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->greenMail->purge();
    }

    public function testForwardsMessageToAllRecipientsAndDeletesOriginal(): void
    {
        $subject = uniqid('Invoice ', true);
        $this->greenMail->deliver(self::DELETE_SOURCE, 'billing@example.org', $subject, 'Original body');

        foreach (['jane', 'wade'] as $recipient) {
            $message = $this->greenMail->awaitMessage($recipient, 'FW: ' . $subject);

            self::assertSame('forwarder@example.com', $message->fromEmail());
            self::assertSame($this->greenMail->email($recipient), $message->replyToEmail());
            self::assertStringContainsString(ucfirst($recipient), $message->text());
            self::assertStringContainsString(
                'a message from billing@example.org to ' . $this->greenMail->email(self::DELETE_SOURCE) . ' was forwarded.',
                $message->text(),
            );

            self::assertCount(1, $message->attachments());

            $original = $message->attachedMessage();
            self::assertSame($subject, $original->subject());
            self::assertSame('billing@example.org', $original->fromEmail());
            self::assertSame($this->greenMail->email(self::DELETE_SOURCE), $original->toEmail());
            self::assertSame('Original body', trim($original->text()));
        }

        $this->greenMail->awaitNoMessage(self::DELETE_SOURCE, $subject);
    }

    public function testForwardsEveryMessageOfTheInbox(): void
    {
        $first  = uniqid('First ', true);
        $second = uniqid('Second ', true);
        $this->greenMail->deliver(self::DELETE_SOURCE, 'a@example.org', $first, 'First body');
        $this->greenMail->deliver(self::DELETE_SOURCE, 'b@example.org', $second, 'Second body');

        foreach (['jane', 'wade'] as $recipient) {
            $forwardedFirst  = $this->greenMail->awaitMessage($recipient, 'FW: ' . $first);
            $forwardedSecond = $this->greenMail->awaitMessage($recipient, 'FW: ' . $second);

            self::assertStringContainsString('a@example.org', $forwardedFirst->text());
            self::assertStringContainsString('First body', $forwardedFirst->attachments()[0] ?? '');
            self::assertStringContainsString('b@example.org', $forwardedSecond->text());
            self::assertStringContainsString('Second body', $forwardedSecond->attachments()[0] ?? '');
        }

        $this->greenMail->awaitNoMessage(self::DELETE_SOURCE, $first);
        $this->greenMail->awaitNoMessage(self::DELETE_SOURCE, $second);
    }

    public function testMovesOriginalToConfiguredFolderAndMarksItAsRead(): void
    {
        $subject = uniqid('Move me ', true);
        $this->greenMail->deliver(self::MOVE_SOURCE, 'billing@example.org', $subject, 'Original body');

        $forwarded = $this->greenMail->awaitMessage('jane', 'FW: ' . $subject);
        self::assertStringContainsString('Moved message from billing@example.org.', $forwarded->text());

        $archived = $this->greenMail->awaitMessage(self::MOVE_SOURCE, $subject, 'Archive');
        self::assertTrue($archived->seen);

        $this->greenMail->awaitNoMessage(self::MOVE_SOURCE, $subject);
    }

    public function testKeepsMovedMessageUnreadWhenMarkAsReadIsDisabled(): void
    {
        $subject = uniqid('Keep me unread ', true);
        $this->greenMail->deliver(self::UNREAD_SOURCE, 'billing@example.org', $subject, 'Original body');

        $this->greenMail->awaitMessage('wade', 'FW: ' . $subject);

        $archived = $this->greenMail->awaitMessage(self::UNREAD_SOURCE, $subject, 'Archive');
        self::assertFalse($archived->seen);

        $this->greenMail->awaitNoMessage(self::UNREAD_SOURCE, $subject);
    }
}
