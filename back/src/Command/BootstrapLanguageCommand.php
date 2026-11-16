<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SymSvel\Module\Languages\Service\LanguageCatalog;

/** Local CLI-only handshake from Setup. No browser can call this command. */
#[AsCommand(name: 'symsvel:languages:bootstrap:stdin')]
final class BootstrapLanguageCommand extends Command
{
    public function __construct(private readonly LanguageCatalog $catalog)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = file_get_contents('php://stdin');
        if (!is_string($raw) || strlen($raw) > 1024) {
            return Command::FAILURE;
        }
        try {
            $data = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_keys($data) !== ['language']
                || !is_string($data['language'])) return Command::FAILURE;
            $this->catalog->initializePrimary($data['language']);
        } catch (\JsonException|\InvalidArgumentException|\DomainException $exception) {
            $output->writeln('<error>Langue principale invalide ou déjà initialisée.</error>');
            return Command::FAILURE;
        }

        $output->writeln('Langue principale configurée.');
        return Command::SUCCESS;
    }
}
