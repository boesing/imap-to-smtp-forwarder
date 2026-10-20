<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Integration;

use RuntimeException;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message as ParsedMessage;

final class ReceivedMessage
{
    private readonly ParsedMessage $parsed;

    public function __construct(
        public readonly string $raw,
        public readonly bool $seen,
    ) {
        $this->parsed = (new MailMimeParser())->parse($this->raw, false);
    }

    public function subject(): string
    {
        return (string) $this->parsed->getHeaderValue(HeaderConsts::SUBJECT);
    }

    public function fromEmail(): string
    {
        return (string) $this->parsed->getHeader(HeaderConsts::FROM)?->getEmail();
    }

    public function toEmail(): string
    {
        return (string) $this->parsed->getHeader(HeaderConsts::TO)?->getEmail();
    }

    public function replyToEmail(): string
    {
        return (string) $this->parsed->getHeader(HeaderConsts::REPLY_TO)?->getEmail();
    }

    public function text(): string
    {
        return (string) $this->parsed->getTextContent();
    }

    /**
     * @return list<string> Decoded content of every attachment.
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->parsed->getAllAttachmentParts() as $part) {
            $attachments[] = (string) $part->getContent();
        }

        return $attachments;
    }

    /**
     * Returns the first attachment parsed as message, which is the original message of a forwarded mail.
     */
    public function attachedMessage(): self
    {
        $attachments = $this->attachments();
        if ($attachments === []) {
            throw new RuntimeException('The message does not have any attachment.');
        }

        return new self($attachments[0], $this->seen);
    }
}
