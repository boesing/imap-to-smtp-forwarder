<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Imap;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Boesing\ImapToSmtpForwarder\Configuration\ImapConfigurationInterface;
use InvalidArgumentException;
use PhpImap\Mailbox;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;

use function sprintf;

use const CL_EXPUNGE;

final class Imap implements ImapInterface
{
    private readonly Mailbox $mailbox;
    private readonly MailMimeParser $mailMimeParser;

    public function __construct(
        private readonly ImapConfigurationInterface $configuration,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        $this->mailbox = new Mailbox(
            imapPath: $this->assembleImapPath($this->configuration, 'INBOX'),
            login: $this->configuration->getUsername(),
            password: $this->configuration->getPassword(),
            serverEncoding: 'UTF-8',
            attachmentFilenameMode: true,
        );
        $this->mailbox->setConnectionArgs(
            CL_EXPUNGE,
        );

        $this->mailMimeParser = new MailMimeParser();
    }

    public function fetch(string $inbox, OutputInterface $output): iterable
    {
        $this->mailbox->switchMailbox($this->assembleImapPath($this->configuration, $inbox));

        foreach ($this->mailbox->searchMailbox() as $id) {
            if ($output->isVerbose()) {
                $output->writeln(sprintf('Found message with id %d', $id));
            }

            $mail   = $this->mailbox->getRawMail($id, false);
            $parsed = $this->mailMimeParser->parse($mail, false);
            $from   = $parsed->getHeader(HeaderConsts::FROM);
            if (! $from instanceof AddressHeader) {
                $output->writeln(sprintf('<error>Message with id %d does not have `From` header.</error>', $id));
                continue;
            }

            $recipients = $parsed->getHeader(HeaderConsts::TO);
            if (! $recipients instanceof AddressHeader) {
                $output->writeln(sprintf('<error>Message with id %d does not have `To` header.</error>', $id));
                continue;
            }

            try {
                yield new Message(
                    $id,
                    $this->streamFactory->createStream($mail),
                    $this->createAddressTransfer($from),
                    $this->createAddressTransfer($recipients),
                    (string) $parsed->getHeaderValue(HeaderConsts::SUBJECT),
                );
            } catch (InvalidArgumentException $exception) {
                $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
                continue;
            }
        }
    }

    public function delete(MessageInterface $messageToForward): void
    {
        $this->mailbox->deleteMail($messageToForward->getMessageId());
    }

    public function move(MessageInterface $messageToForward, string $inboxToMove): void
    {
        $this->mailbox->moveMail($messageToForward->getMessageId(), $inboxToMove);
    }

    private function createAddressTransfer(AddressHeader $header): AddressTransfer
    {
        $email = $header->getEmail();
        if ($email === null || $email === '') {
            throw new InvalidArgumentException('Provided header does not contain an email address.');
        }

        $personName = $header->getPersonName();
        if ($personName === '') {
            $personName = null;
        }

        return new AddressTransfer($email, $personName);
    }

    /**
     * @return non-empty-string
     */
    private function assembleImapPath(ImapConfigurationInterface $configuration, string $inbox): string
    {
        return sprintf('{%s:%d/service=imap/ssl}%s', $configuration->getHostname(), $configuration->getPort(), $inbox);
    }
}
