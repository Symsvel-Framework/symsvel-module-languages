<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Service;

use SymSvel\Module\Settings\Service\SettingDefinition;
use SymSvel\Module\Settings\Service\SettingDefinitionProviderInterface;

/** Language configuration is owned by Languages, persisted by Settings. */
final class LanguagesSettingDefinitionProvider implements SettingDefinitionProviderInterface
{
    public function definitions(): iterable
    {
        yield new SettingDefinition(
            key: 'primary_language',
            label: 'Langue principale',
            group: 'languages',
            required: true,
            blocking: true,
            editable: false,
            description: 'Initialisée depuis la langue du navigateur au Setup ; non modifiable ensuite.',
        );
        yield new SettingDefinition(
            key: 'multilingual',
            label: 'Site multilingue',
            group: 'languages',
            type: 'boolean',
            description: 'Afficher les langues actives ou uniquement la langue principale.',
        );
    }
}
