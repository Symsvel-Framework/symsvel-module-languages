<?php

declare(strict_types=1);

namespace SymSvel\Module\SymsvelModuleLanguages\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\SymsvelModuleLanguages\SymsvelModuleLanguagesBundle;

final class SymsvelModuleLanguagesBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        self::assertInstanceOf(SymsvelModuleLanguagesBundle::class, new SymsvelModuleLanguagesBundle());
    }
}
