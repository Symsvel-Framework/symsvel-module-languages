<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Languages\LanguagesBundle;

final class LanguagesBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        self::assertInstanceOf(LanguagesBundle::class, new LanguagesBundle());
    }
}
