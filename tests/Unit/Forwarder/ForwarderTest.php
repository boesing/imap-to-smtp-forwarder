<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Unit\Forwarder;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Boesing\ImapToSmtpForwarder\Configuration\DeleteActionInterface;
use Boesing\ImapToSmtpForwarder\Configuration\MoveAction;
use Boesing\ImapToSmtpForwarder\Configuration\MoveActionInterface;
use Boesing\ImapToSmtpForwarder\Forwarder\Forwarder;
use Boesing\ImapToSmtpForwarder\Imap\ImapInterface;
use Boesing\ImapToSmtpForwarder\Imap\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(Forwarder::class)]
final class ForwarderTest extends TestCase
{
    private const RAW_MESSAGE = "From: sender@example.com\r\nTo: original@example.com\r\nSubject: Hello\r\n\r\nBody";

    public function testForwardsOriginalMessageAsAttachmentToEveryDestination(): void
    {
        $imap = self::createStub(ImapInterface::class);
        $imap->method('fetch')->willReturn([$this->createMessage()]);

        $sent   = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(
            static function (RawMessage $message) use (&$sent): void {
                $sent[] = $message;
            },
        );

        $unit = $this->createUnit(
            imap: $imap,
            forwardDestinations: [
                new AddressTransfer('jane@example.com', 'Jane Doe'),
                new AddressTransfer('wade@example.com', 'Wade Doe'),
            ],
            forwardSender: new AddressTransfer('forwarder@example.com', 'Forwarder'),
            mailer: $mailer,
        );

        $unit->forward(new BufferedOutput());

        self::assertCount(2, $sent);

        foreach ([['jane@example.com', 'Jane Doe'], ['wade@example.com', 'Wade Doe']] as $index => [$email, $name]) {
            $mail = $sent[$index];
            self::assertInstanceOf(Email::class, $mail);
            self::assertSame('FW: Hello', $mail->getSubject());

            // Each mail must only be addressed to a single destination.
            self::assertCount(1, $mail->getTo());
            self::assertSame($email, $mail->getTo()[0]->getAddress());
            self::assertSame($name, $mail->getTo()[0]->getName());

            self::assertCount(1, $mail->getFrom());
            self::assertSame('forwarder@example.com', $mail->getFrom()[0]->getAddress());
            self::assertSame('Forwarder', $mail->getFrom()[0]->getName());

            self::assertCount(1, $mail->getReplyTo());
            self::assertSame($email, $mail->getReplyTo()[0]->getAddress());

            $attachments = $mail->getAttachments();
            self::assertCount(1, $attachments);
            self::assertSame('Original.eml', $attachments[0]->getFilename());
            self::assertSame('message', $attachments[0]->getMediaType());
            self::assertSame('rfc822', $attachments[0]->getMediaSubtype());
            self::assertSame(self::RAW_MESSAGE, $attachments[0]->getBody());
        }
    }

    public function testReplacesTemplatePlaceholders(): void
    {
        $imap = self::createStub(ImapInterface::class);
        $imap->method('fetch')->willReturn([
            $this->createMessage(
                from: new AddressTransfer('sender@example.com', 'Sender Name'),
                to: new AddressTransfer('original@example.com', 'Original Name'),
            ),
        ]);

        $sent   = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            static function (RawMessage $message) use (&$sent): void {
                $sent[] = $message;
            },
        );

        $unit = $this->createUnit(
            imap: $imap,
            template: $this->createStream(
                "Hello %recipient.name%,\n%message.from.name% <%message.from.email%> wrote to %message.to.name% <%message.to.email%>",
            ),
            forwardDestinations: [new AddressTransfer('jane@example.com', 'Jane')],
            mailer: $mailer,
        );

        $unit->forward(new BufferedOutput());

        $mail = $sent[0];
        self::assertInstanceOf(Email::class, $mail);
        self::assertSame(
            "Hello Jane,\nSender Name <sender@example.com> wrote to Original Name <original@example.com>",
            $mail->getTextBody(),
        );
    }

    public function testReplacesPlaceholdersOfMissingNamesWithEmptyStrings(): void
    {
        $imap = self::createStub(ImapInterface::class);
        $imap->method('fetch')->willReturn([$this->createMessage()]);

        $sent   = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            static function (RawMessage $message) use (&$sent): void {
                $sent[] = $message;
            },
        );

        $unit = $this->createUnit(
            imap: $imap,
            template: $this->createStream('[%recipient.name%][%message.from.name%][%message.to.name%]'),
            forwardDestinations: [new AddressTransfer('jane@example.com', null)],
            mailer: $mailer,
        );

        $unit->forward(new BufferedOutput());

        $mail = $sent[0];
        self::assertInstanceOf(Email::class, $mail);
        self::assertSame('[][][]', $mail->getTextBody());
        self::assertSame('', $mail->getTo()[0]->getName());
    }

    public function testFetchesConfiguredInbox(): void
    {
        $output = new BufferedOutput();

        $imap = $this->createMock(ImapInterface::class);
        $imap->expects(self::once())->method('fetch')->with('INBOX.Netflix', $output)->willReturn([]);

        $this->createUnit(imap: $imap, inboxToWatch: 'INBOX.Netflix')->forward($output);
    }

    public function testDoesNothingWithoutMessages(): void
    {
        $imap = $this->createMock(ImapInterface::class);
        $imap->method('fetch')->willReturn([]);
        $imap->expects(self::never())->method('delete');
        $imap->expects(self::never())->method('move');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->createUnit(imap: $imap, mailer: $mailer)->forward(new BufferedOutput());
    }

    public function testDeletesMessageAfterForwarding(): void
    {
        $message = $this->createMessage();

        $imap = $this->createMock(ImapInterface::class);
        $imap->method('fetch')->willReturn([$message]);
        $imap->expects(self::once())->method('delete')->with($message);
        $imap->expects(self::never())->method('move');

        $this->createUnit(imap: $imap, action: self::createStub(DeleteActionInterface::class))
            ->forward(new BufferedOutput());
    }

    public function testMovesMessageAfterForwarding(): void
    {
        $message = $this->createMessage();

        $imap = $this->createMock(ImapInterface::class);
        $imap->method('fetch')->willReturn([$message]);
        $imap->expects(self::once())->method('move')->with($message, 'Archive', false);
        $imap->expects(self::never())->method('delete');

        $this->createUnit(imap: $imap, action: new MoveAction('Archive', false))
            ->forward(new BufferedOutput());
    }

    public function testKeepsMessageInInboxWhenSendingFails(): void
    {
        $imap = $this->createMock(ImapInterface::class);
        $imap->method('fetch')->willReturn([$this->createMessage()]);
        $imap->expects(self::never())->method('delete');
        $imap->expects(self::never())->method('move');

        $mailer = self::createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP is down'));

        $unit = $this->createUnit(imap: $imap, mailer: $mailer, action: self::createStub(DeleteActionInterface::class));

        $this->expectException(TransportException::class);

        $unit->forward(new BufferedOutput());
    }

    public function testReportsForwardedMessages(): void
    {
        $imap = self::createStub(ImapInterface::class);
        $imap->method('fetch')->willReturn([$this->createMessage()]);

        $output = new BufferedOutput();

        $this->createUnit(
            imap: $imap,
            forwardDestinations: [new AddressTransfer('jane@example.com', null)],
        )->forward($output);

        self::assertSame(
            'Forwarded message from sender@example.com to jane@example.com' . "\n",
            $output->fetch(),
        );
    }

    /**
     * @param non-empty-list<AddressTransfer>|null $forwardDestinations
     * @param non-empty-string|null                $inboxToWatch
     */
    private function createUnit(
        ImapInterface|null $imap = null,
        StreamInterface|null $template = null,
        array|null $forwardDestinations = null,
        AddressTransfer|null $forwardSender = null,
        MailerInterface|null $mailer = null,
        DeleteActionInterface|MoveActionInterface|null $action = null,
        string|null $inboxToWatch = null,
    ): Forwarder {
        return new Forwarder(
            $imap ?? self::createStub(ImapInterface::class),
            $template ?? self::createStub(StreamInterface::class),
            $forwardDestinations ?? [new AddressTransfer('recipient@example.com', null)],
            $forwardSender ?? new AddressTransfer('forwarder@example.com', null),
            $mailer ?? self::createStub(MailerInterface::class),
            $action ?? self::createStub(MoveActionInterface::class),
            $inboxToWatch ?? 'INBOX',
        );
    }

    private function createStream(string $contents): StreamInterface
    {
        $stream = self::createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($contents);

        return $stream;
    }

    private function createMessage(
        AddressTransfer|null $from = null,
        AddressTransfer|null $to = null,
        string $subject = 'Hello',
    ): Message {
        return new Message(
            1,
            $this->createStream(self::RAW_MESSAGE),
            $from ?? new AddressTransfer('sender@example.com', null),
            $to ?? new AddressTransfer('original@example.com', null),
            $subject,
        );
    }
}
