<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Tests\Unit\Command;

use Boesing\ImapToSmtpForwarder\Command\StartCommand;
use Boesing\ImapToSmtpForwarder\Configuration\ConfigurationInterface;
use Boesing\ImapToSmtpForwarder\Configuration\ForwardConfigurationInterface;
use Boesing\ImapToSmtpForwarder\Forwarder\ForwarderFactoryInterface;
use Boesing\ImapToSmtpForwarder\Forwarder\ForwarderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(StartCommand::class)]
final class StartCommandTest extends TestCase
{
    public function testFailsWithoutForwards(): void
    {
        $configuration = self::createStub(ConfigurationInterface::class);
        $configuration->method('getForwardConfiguration')->willReturn([]);

        $forwarderFactory = $this->createMock(ForwarderFactoryInterface::class);
        $forwarderFactory->expects(self::never())->method('createForwarderFromConfiguration');

        $tester = new CommandTester($this->createUnit($configuration, $forwarderFactory));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Configuration does not contain any forwards.', $tester->getDisplay());
    }

    public function testRunsEveryForwarderOnceWithoutDaemonOption(): void
    {
        $configuration = self::createStub(ConfigurationInterface::class);
        $configuration->method('getForwardConfiguration')->willReturn([
            self::createStub(ForwardConfigurationInterface::class),
            self::createStub(ForwardConfigurationInterface::class),
        ]);

        $forwarder = $this->createMock(ForwarderInterface::class);
        $forwarder->expects(self::exactly(2))->method('forward')->with(self::isInstanceOf(OutputInterface::class));

        $forwarderFactory = self::createStub(ForwarderFactoryInterface::class);
        $forwarderFactory->method('createForwarderFromConfiguration')->willReturn($forwarder);

        $tester = new CommandTester($this->createUnit($configuration, $forwarderFactory));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testKeepsForwardingWhenStartedAsDaemon(): void
    {
        $configuration = self::createStub(ConfigurationInterface::class);
        $configuration->method('getForwardConfiguration')->willReturn([
            self::createStub(ForwardConfigurationInterface::class),
        ]);
        $configuration->method('getLoopDelay')->willReturn(1);

        $calls     = 0;
        $forwarder = $this->createMock(ForwarderInterface::class);
        $forwarder->expects(self::exactly(2))->method('forward')->willReturnCallback(
            static function () use (&$calls): void {
                if (++$calls === 2) {
                    throw new RuntimeException('Stop the daemon loop.');
                }
            },
        );

        $forwarderFactory = self::createStub(ForwarderFactoryInterface::class);
        $forwarderFactory->method('createForwarderFromConfiguration')->willReturn($forwarder);

        $tester = new CommandTester($this->createUnit($configuration, $forwarderFactory));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stop the daemon loop.');

        $tester->execute(['--daemon' => true]);
    }

    private function createUnit(
        ConfigurationInterface|null $configuration = null,
        ForwarderFactoryInterface|null $forwarderFactory = null,
    ): StartCommand {
        return new StartCommand(
            $configuration ?? self::createStub(ConfigurationInterface::class),
            $forwarderFactory ?? self::createStub(ForwarderFactoryInterface::class),
        );
    }
}
