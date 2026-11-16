<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Permission;

use SymSvel\Module\Acls\Permission\PermissionDefinition;
use SymSvel\Module\Acls\Permission\PermissionProviderInterface;

final class LanguagesPermissionProvider implements PermissionProviderInterface
{
    public function permissions(): iterable
    {
        yield new PermissionDefinition('languages', 'list', 'Consulter les langues');
        yield new PermissionDefinition('languages', 'manage', 'Modifier les langues disponibles');
    }
}
