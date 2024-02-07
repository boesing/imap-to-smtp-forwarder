<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Command;

use Boesing\ImapToSmtpForwarder\Configuration\ConfigurationInterface;
use Boesing\ImapToSmtpForwarder\Forwarder\ForwarderFactoryInterface;
use Boesing\ImapToSmtpForwarder\Forwarder\ForwarderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sleep;

final class StartCommand extends Command
{
    public const NAME = 'start';

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly ForwarderFactoryInterface $forwarderFactory,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Start as daemon.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $daemonize = $input->getOption('daemon') === true;

        $forwarders = [];
        foreach ($this->configuration->getForwardConfiguration() as $forwardConfiguration) {
            $forwarders[] = $this->forwarderFactory->createForwarderFromConfiguration($forwardConfiguration);
        }

        if ($forwarders === []) {
            $output->writeln('Configuration does not contain any forwards.');

            return self::FAILURE;
        }

        $this->forward($output, $forwarders, $daemonize);

        return self::SUCCESS;
    }

    /**
     * @param non-empty-list<ForwarderInterface> $forwarders
     */
    private function forward(OutputInterface $output, array $forwarders, bool $daemonize): void
    {
        while (true) {
            foreach ($forwarders as $forwarder) {
                $forwarder->forward($output);
            }

            if ($daemonize === false) {
                break;
            }

            sleep($this->configuration->getLoopDelay());
        }
    }
}
