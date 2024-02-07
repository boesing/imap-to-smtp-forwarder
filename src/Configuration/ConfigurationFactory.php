<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfiguration;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationAddress;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationForward;
use Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration\JsonConfigurationForwardActionEnum;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\JsonSource;
use CuyZ\Valinor\Mapper\TreeMapper;
use InvalidArgumentException;
use Psr\Http\Message\StreamFactoryInterface;

use function array_map;
use function assert;
use function file_get_contents;
use function implode;
use function is_file;
use function is_readable;
use function is_string;
use function json_validate;
use function sprintf;

final class ConfigurationFactory implements ConfigurationFactoryInterface
{
    public function __construct(
        private readonly StreamFactoryInterface $streamFactory,
        private readonly TreeMapper $mapper,
    ) {
    }

    public function createFromConfigurationFile(string $pathToConfigurationFile): ConfigurationInterface
    {
        if (! is_readable($pathToConfigurationFile) || ! is_file($pathToConfigurationFile)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to read configuration file: %s',
                $pathToConfigurationFile,
            ));
        }

        $jsonSource = file_get_contents($pathToConfigurationFile);
        if (! is_string($jsonSource) || ! json_validate($jsonSource)) {
            throw new InvalidArgumentException(sprintf('Provided configuration is not a valid JSON configuration: %s', $pathToConfigurationFile));
        }

        $source = new JsonSource($jsonSource);
        try {
            $configuration = $this->mapper->map(JsonConfiguration::class, $source);
        } catch (MappingError $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided configuration is not a valid JSON configuration: %s',
                    $pathToConfigurationFile,
                ),
                previous: $exception,
            );
        }

        return new Configuration($this->createForwardsFromConfiguration($configuration));
    }

    /**
     * @return non-empty-list<ForwardConfigurationInterface>
     */
    private function createForwardsFromConfiguration(JsonConfiguration $configuration): array
    {
        $forwards = [];
        foreach ($configuration->forwards as $forward) {
            $imap = $configuration->imap[$forward->imap] ?? null;
            if ($imap === null) {
                throw new InvalidArgumentException(sprintf(
                    'Missing IMAP configuration identified by: %s',
                    $forward->imap,
                ));
            }

            $smtp = $configuration->smtp[$forward->smtp->identifier] ?? null;
            if ($smtp === null) {
                throw new InvalidArgumentException(sprintf(
                    'Missing SMTP configuration identified by: %s',
                    $forward->smtp->identifier,
                ));
            }

            $targets = array_map(
                fn (string $identifier) => $this->extractAddress($configuration->addresses, $identifier),
                $forward->recipients,
            );

            $sender = $this->extractAddress($configuration->addresses, $forward->smtp->sender);

            if (! isset($configuration->templates[$forward->template])) {
                throw new InvalidArgumentException(sprintf('Missing template identified by: %s', $forward->template));
            }

            $forwards[] = new ForwardConfiguration(
                new ImapConfiguration(
                    $forward->imap,
                    $imap->hostname,
                    $imap->port,
                    $imap->username,
                    $imap->password,
                ),
                new SmtpConfiguration(
                    $forward->smtp->identifier,
                    new AddressTransfer($sender->email, $sender->name),
                    $smtp->username,
                    $smtp->password,
                    $smtp->hostname,
                    $smtp->port,
                ),
                $targets,
                $this->streamFactory->createStream(implode("\n", $configuration->templates[$forward->template])),
                $this->createImapAction($forward),
                $forward->inbox,
            );
        }

        return $forwards;
    }

    /**
     * @param non-empty-array<non-empty-string,JsonConfigurationAddress> $targets
     * @param non-empty-string                                           $identifier
     */
    private function extractAddress(array $targets, string $identifier): AddressTransfer
    {
        if (! isset($targets[$identifier])) {
            throw new InvalidArgumentException(sprintf('Missing target configuration identified by: %s', $identifier));
        }

        $target = $targets[$identifier];

        return new AddressTransfer($target->email, $target->name);
    }

    private function createImapAction(
        JsonConfigurationForward $configuration,
    ): DeleteActionInterface|MoveActionInterface {
        return match ($configuration->action) {
            JsonConfigurationForwardActionEnum::DELETE => new class implements DeleteActionInterface {
            },
            JsonConfigurationForwardActionEnum::MOVE => $this->createMoveAction($configuration),
        };
    }

    private function createMoveAction(JsonConfigurationForward $configuration): MoveActionInterface
    {
        assert($configuration->inboxToMove !== null);

        return new MoveAction($configuration->inboxToMove);
    }
}
