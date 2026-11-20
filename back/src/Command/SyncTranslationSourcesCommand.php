<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use SymSvel\Module\Languages\Service\TranslationSourceSynchronizer;

/** Tooling invokes this local operation after compiling module migrations. */
#[AsCommand(name: 'symsvel:languages:translations:sync', description: 'Import installed translation sources after migrations')]
final class SyncTranslationSourcesCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $backend,
        private readonly TranslationSourceSynchronizer $sources,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (basename($this->backend) !== 'backend') {
                throw new \RuntimeException('Expected SymSvel backend directory.');
            }
            $result = $this->sources->sync($this->backend.'/.symsvel/translation-sources.json');
            $output->writeln(sprintf(
                '<info>Translation sources synchronized: %d keys, %d new texts, %d updates, %d protected.</info>',
                $result['keys_created'], $result['texts_created'], $result['texts_updated'], $result['protected'],
            ));
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            $output->writeln('<error>'.$error->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
