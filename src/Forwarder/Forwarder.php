<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Forwarder;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Boesing\ImapToSmtpForwarder\Configuration\DeleteActionInterface;
use Boesing\ImapToSmtpForwarder\Configuration\MoveActionInterface;
use Boesing\ImapToSmtpForwarder\Imap\ImapInterface;
use Boesing\ImapToSmtpForwarder\Imap\MessageInterface;
use Override;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

use function array_keys;
use function array_values;
use function sprintf;
use function str_replace;

final readonly class Forwarder implements ForwarderInterface
{
    /**
     * @param non-empty-list<AddressTransfer> $forwardDestinations
     * @param non-empty-string                $inboxToWatch
     */
    public function __construct(
        private ImapInterface $imap,
        private StreamInterface $template,
        private array $forwardDestinations,
        private AddressTransfer $forwardSender,
        private MailerInterface $mailer,
        private DeleteActionInterface|MoveActionInterface $action,
        public string $inboxToWatch,
    ) {
    }

    #[Override]
    public function forward(OutputInterface $output): void
    {
        foreach ($this->imap->fetch($this->inboxToWatch, $output) as $messageToForward) {
            $message = (new Email())
                ->subject(sprintf('FW: %s', $messageToForward->getSubject()))
                ->addPart(new DataPart(
                    (string) $messageToForward->getRawMessage(),
                    'Original.eml',
                    'message/rfc822',
                ));

            foreach ($this->forwardDestinations as $forwardDestination) {
                $messageToDestination = clone $message;
                $messageToDestination
                    ->text($this->parseTemplate($this->template, $messageToForward, $forwardDestination), 'utf-8')
                    ->addTo(new Address($forwardDestination->email, $forwardDestination->name ?? ''))
                    ->addFrom(new Address($this->forwardSender->email, $this->forwardSender->name ?? ''))
                    ->addReplyTo(new Address($forwardDestination->email, $forwardDestination->name ?? ''));

                $this->mailer->send($messageToDestination);
                $output->writeln(sprintf('Forwarded message from %s to %s', $messageToForward->getMailFromAddress()->email, $forwardDestination->email));
            }

            $this->executeForwardAction($this->action, $messageToForward);
        }
    }

    private function parseTemplate(
        StreamInterface $template,
        MessageInterface $messageToForward,
        AddressTransfer $recipient,
    ): string {
        $replacements = [
            '%recipient.name%' => $recipient->name ?? '',
            '%message.from.name%' => $messageToForward->getMailFromAddress()->name ?? '',
            '%message.from.email%' => $messageToForward->getMailFromAddress()->email,
            '%message.to.name%' => $messageToForward->getMailToAddress()->name ?? '',
            '%message.to.email%' => $messageToForward->getMailToAddress()->email,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), (string) $template);
    }

    private function executeForwardAction(DeleteActionInterface|MoveActionInterface $action, MessageInterface $messageToForward): void
    {
        if ($action instanceof DeleteActionInterface) {
            $this->imap->delete($messageToForward);

            return;
        }

        $this->imap->move($messageToForward, $action->getInboxToMove(), $action->markAsRead());
    }
}
