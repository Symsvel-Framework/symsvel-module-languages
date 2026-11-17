<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Languages\Controller\BoTranslationsController;

final class TranslationRouteEnvironmentTest extends TestCase
{
    public function testImportAndSourcePublicationRoutesAreExcludedFromProduction(): void
    {
        foreach ([
            'importCsv' => ['dev', 'test'],
            'publish' => 'dev',
        ] as $method => $environment) {
            $attributes = (new \ReflectionMethod(BoTranslationsController::class, $method))
                ->getAttributes(Route::class);
            self::assertCount(1, $attributes);
            self::assertSame($environment, $attributes[0]->getArguments()['env']);
            self::assertSame(['POST'], $attributes[0]->getArguments()['methods']);
        }
    }

    public function testCsvExportRemainsAvailableToProductionAdmins(): void
    {
        $attributes = (new \ReflectionMethod(BoTranslationsController::class, 'exportCsv'))
            ->getAttributes(Route::class);
        self::assertCount(1, $attributes);
        self::assertArrayNotHasKey('env', $attributes[0]->getArguments());
    }
}
