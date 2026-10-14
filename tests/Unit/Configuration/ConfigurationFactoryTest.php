<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Unit\Configuration;

use Boesing\ImapToSmtpForwarder\Configuration\ConfigurationFactory;
use Boesing\ImapToSmtpForwarder\Configuration\DeleteActionInterface;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfiguration;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationAddress;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationForward;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationForwardActionEnum;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationForwardSmtp;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationImap;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationSmtp;
use Boesing\ImapToSmtpForwarder\Configuration\MoveActionInterface;
use CuyZ\Valinor\Mapper\Source\JsonSource;
use CuyZ\Valinor\Mapper\TreeMapper;
use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function array_map;
use function file_put_contents;
use function iterator_to_array;
use function sprintf;

/**
 * The mapping of the JSON file to {@see JsonConfiguration} is done by Valinor and therefore not covered here.
 */
#[CoversClass(ConfigurationFactory::class)]
final class ConfigurationFactoryTest extends TestCase
{
    private vfsStreamDirectory $root;
    private int $files = 0;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup();
    }

    public function testCreatesConfigurationWithMoveAction(): void
    {
        $template = self::createStub(StreamInterface::class);

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->expects(self::once())
            ->method('createStream')
            ->with("Hello %recipient.name%,\n\nBye")
            ->willReturn($template);

        $configuration = $this->createUnit(
            $streamFactory,
            $this->createMapper($this->createJsonConfiguration()),
        )->createFromConfigurationFile($this->createConfigurationFile('{}'));

        self::assertSame(5, $configuration->getLoopDelay());

        $forwards = iterator_to_array($configuration->getForwardConfiguration(), false);
        self::assertCount(1, $forwards);

        $forward = $forwards[0];
        self::assertSame('INBOX', $forward->getInboxToWatch());
        self::assertSame($template, $forward->getTemplate());

        $imap = $forward->getImapConfiguration();
        self::assertSame('main', $imap->getIdentifier());
        self::assertSame('imap.example.com', $imap->getHostname());
        self::assertSame(993, $imap->getPort());
        self::assertSame('imap-user', $imap->getUsername());
        self::assertSame('imap-secret', $imap->getPassword());

        $smtp = $forward->getSmtpConfiguration();
        self::assertSame('main', $smtp->getIdentifier());
        self::assertSame('smtp.example.com', $smtp->getHostname());
        self::assertSame(25, $smtp->getPort());
        self::assertSame('smtp-user', $smtp->getUsername());
        self::assertSame('smtp-secret', $smtp->getPassword());
        self::assertSame('sender@example.com', $smtp->getSender()->email);
        self::assertSame('Sender', $smtp->getSender()->name);

        self::assertSame(
            ['jane@example.com', 'john@example.com'],
            array_map(
                static fn ($destination) => $destination->email,
                $forward->getForwardDestinations(),
            ),
        );

        $action = $forward->getActionAfterForward();
        self::assertInstanceOf(MoveActionInterface::class, $action);
        self::assertSame('Archive', $action->getInboxToMove());
        self::assertTrue($action->markAsRead());
    }

    public function testCreatesConfigurationWithDeleteAction(): void
    {
        $configuration = $this->createUnit(
            mapper: $this->createMapper($this->createJsonConfiguration(
                forward: $this->createForward(action: JsonConfigurationForwardActionEnum::DELETE, inboxToMove: null),
            )),
        )->createFromConfigurationFile($this->createConfigurationFile('{}'));

        $forward = iterator_to_array($configuration->getForwardConfiguration(), false)[0];

        self::assertInstanceOf(DeleteActionInterface::class, $forward->getActionAfterForward());
    }

    public function testRespectsOptionalValues(): void
    {
        $configuration = $this->createUnit(
            mapper: $this->createMapper($this->createJsonConfiguration(
                forward: $this->createForward(inbox: 'INBOX.Netflix', markAsRead: false),
                loopDelay: 30,
            )),
        )->createFromConfigurationFile($this->createConfigurationFile('{}'));

        $forward = iterator_to_array($configuration->getForwardConfiguration(), false)[0];
        $action  = $forward->getActionAfterForward();

        self::assertSame(30, $configuration->getLoopDelay());
        self::assertSame('INBOX.Netflix', $forward->getInboxToWatch());
        self::assertInstanceOf(MoveActionInterface::class, $action);
        self::assertFalse($action->markAsRead());
    }

    /**
     * @param array<string, mixed> $forwardOverrides
     */
    #[DataProvider('provideReferencesToMissingConfiguration')]
    public function testFailsWhenForwardReferencesMissingConfiguration(
        array $forwardOverrides,
        string $expectedMessage,
    ): void {
        $unit = $this->createUnit(
            mapper: $this->createMapper($this->createJsonConfiguration(
                forward: $this->createForward(...$forwardOverrides),
            )),
        );
        $file = $this->createConfigurationFile('{}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs($expectedMessage);

        $unit->createFromConfigurationFile($file);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideReferencesToMissingConfiguration(): iterable
    {
        yield 'imap' => [['imap' => 'unknown'], 'Missing IMAP configuration identified by: unknown'];
        yield 'smtp' => [['smtp' => 'unknown'], 'Missing SMTP configuration identified by: unknown'];
        yield 'recipient' => [['recipients' => ['jane', 'unknown']], 'Missing target configuration identified by: unknown'];
        yield 'sender' => [['sender' => 'unknown'], 'Missing target configuration identified by: unknown'];
        yield 'template' => [['template' => 'unknown'], 'Missing template identified by: unknown'];
    }

    public function testFailsWhenConfigurationFileDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Unable to read configuration file: /does/not/exist.json');

        $this->createUnit()->createFromConfigurationFile('/does/not/exist.json');
    }

    public function testFailsWhenConfigurationIsNoValidJson(): void
    {
        $mapper = $this->createMock(TreeMapper::class);
        $mapper->expects(self::never())->method('map');

        $file = $this->createConfigurationFile('{ this is not json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Provided configuration is not a valid JSON configuration');

        $this->createUnit(mapper: $mapper)->createFromConfigurationFile($file);
    }

    public function testMapsTheContentsOfTheConfigurationFile(): void
    {
        $mapper = $this->createMock(TreeMapper::class);
        $mapper->expects(self::once())
            ->method('map')
            ->with(JsonConfiguration::class, self::isInstanceOf(JsonSource::class))
            ->willReturn($this->createJsonConfiguration());

        $this->createUnit(mapper: $mapper)->createFromConfigurationFile($this->createConfigurationFile('{"a": 1}'));
    }

    private function createUnit(
        StreamFactoryInterface|null $streamFactory = null,
        TreeMapper|null $mapper = null,
    ): ConfigurationFactory {
        return new ConfigurationFactory(
            $streamFactory ?? self::createStub(StreamFactoryInterface::class),
            $mapper ?? self::createStub(TreeMapper::class),
        );
    }

    private function createMapper(JsonConfiguration $configuration): TreeMapper
    {
        $mapper = self::createStub(TreeMapper::class);
        $mapper->method('map')->willReturn($configuration);

        return $mapper;
    }

    /**
     * @param non-empty-string|null            $inboxToMove
     * @param non-empty-list<non-empty-string> $recipients
     * @param non-empty-string                 $inbox
     * @param non-empty-string                 $imap
     * @param non-empty-string                 $smtp
     * @param non-empty-string                 $sender
     * @param non-empty-string                 $template
     */
    private function createForward(
        string $imap = 'main',
        string $smtp = 'main',
        string $sender = 'sender',
        array $recipients = ['jane', 'john'],
        string $template = 'default',
        JsonConfigurationForwardActionEnum $action = JsonConfigurationForwardActionEnum::MOVE,
        string $inbox = 'INBOX',
        string|null $inboxToMove = 'Archive',
        bool $markAsRead = true,
    ): JsonConfigurationForward {
        return new JsonConfigurationForward(
            $imap,
            new JsonConfigurationForwardSmtp($smtp, $sender),
            $recipients,
            $template,
            $action,
            $inbox,
            $inboxToMove,
            $markAsRead,
        );
    }

    /**
     * @param positive-int $loopDelay
     */
    private function createJsonConfiguration(
        JsonConfigurationForward|null $forward = null,
        int $loopDelay = 5,
    ): JsonConfiguration {
        return new JsonConfiguration(
            [
                'john' => new JsonConfigurationAddress('John Doe', 'john@example.com'),
                'jane' => new JsonConfigurationAddress('Jane Doe', 'jane@example.com'),
                'sender' => new JsonConfigurationAddress('Sender', 'sender@example.com'),
            ],
            ['default' => ['Hello %recipient.name%,', '', 'Bye']],
            ['main' => new JsonConfigurationImap('imap.example.com', 'imap-user', 'imap-secret', 993)],
            ['main' => new JsonConfigurationSmtp('smtp.example.com', 'smtp-user', 'smtp-secret', 25)],
            [$forward ?? $this->createForward()],
            $loopDelay,
        );
    }

    private function createConfigurationFile(string $contents): string
    {
        $file = sprintf('%s/configuration-%d.json', $this->root->url(), ++$this->files);
        file_put_contents($file, $contents);

        return $file;
    }
}
