<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymSvel\Module\Languages\Command\BootstrapLanguageCommand;
use SymSvel\Module\Languages\Command\SyncTranslationSourcesCommand;
use SymSvel\Module\Languages\Controller\BoLanguagesController;
use SymSvel\Module\Languages\Controller\BoTranslationsController;
use SymSvel\Module\Languages\Controller\PublicLanguagesController;
use SymSvel\Module\Languages\Controller\SetupLanguagesController;
use SymSvel\Module\Languages\Permission\LanguagesPermissionProvider;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Languages\Service\TranslationCatalog;
use SymSvel\Module\Languages\Service\TranslationCsvTransfer;
use SymSvel\Module\Languages\Service\TranslationSourceSynchronizer;
use SymSvel\Module\Languages\Service\LanguageLocaleResolver;
use SymSvel\Module\Languages\Service\LanguagesSettingDefinitionProvider;

final class LanguagesBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->register(LanguagesSettingDefinitionProvider::class, LanguagesSettingDefinitionProvider::class)
            ->addTag('symsvel.settings.definition_provider');
        $builder->register(LanguagesPermissionProvider::class, LanguagesPermissionProvider::class)
            ->addTag('symsvel.acls.permission_provider');
        foreach ([LanguageCatalog::class, LanguageLocaleResolver::class, TranslationCatalog::class, TranslationCsvTransfer::class, TranslationSourceSynchronizer::class] as $service) {
            $builder->register($service, $service)->setAutowired(true);
        }
        foreach ([BoLanguagesController::class, BoTranslationsController::class, PublicLanguagesController::class, SetupLanguagesController::class] as $controller) {
            $builder->register($controller, $controller)->setAutowired(true)
                ->addTag('controller.service_arguments');
        }
        $builder->register(BootstrapLanguageCommand::class, BootstrapLanguageCommand::class)
            ->setAutowired(true)->addTag('console.command', ['command' => 'symsvel:languages:bootstrap:stdin']);
        $builder->register(SyncTranslationSourcesCommand::class, SyncTranslationSourcesCommand::class)
            ->setAutowired(true)->addTag('console.command', ['command' => 'symsvel:languages:translations:sync']);
    }
}
