<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Languages\Service\LanguageLocaleResolver;
use SymSvel\Module\Languages\Service\LanguagesSettingDefinitionProvider;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

final class LanguageCatalogTest extends TestCase
{
    /** @return array{LanguageCatalog, SettingManager, Connection} */
    private function services(): array
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite required.');
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname((new \ReflectionClass(Setting::class))->getFileName())],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($connection, $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Setting::class)]);

        $schema = new Schema();
        $table = $schema->createTable('symsvel_languages');
        $table->addColumn('iso_code', 'string', ['length' => 20]);
        $table->addColumn('name', 'string', ['length' => 150]);
        $table->addColumn('active', 'boolean', ['default' => false]);
        $table->addColumn('pre_open', 'boolean', ['default' => false]);
        $table->setPrimaryKey(['iso_code']);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
        foreach (['de' => 'Deutsch', 'en' => 'English', 'fr' => 'français'] as $code => $name) {
            $connection->insert('symsvel_languages', [
                'iso_code' => $code, 'name' => $name, 'active' => false, 'pre_open' => false,
            ]);
        }

        $doctrineRegistry = $this->createMock(ManagerRegistry::class);
        $doctrineRegistry->method('getManagerForClass')->with(Setting::class)->willReturn($em);
        $registry = new SettingRegistry([]);
        $registry->registerOwned(new LanguagesSettingDefinitionProvider(), 'languages', new SettingKeyQualifier());
        $settings = new SettingManager($registry, new SettingRepository($doctrineRegistry), $em,
            new SettingSecretCipher('test-secret'));
        return [new LanguageCatalog($connection, $settings), $settings, $connection];
    }

    public function testInitialCatalogIsInactiveAndSetupChoosesExactlyOnePrimary(): void
    {
        [$catalog, $settings, $db] = $this->services();
        try {
            self::assertCount(3, $catalog->allForAdministration());
            self::assertSame([], $catalog->availableForAdministration());
            self::assertSame([], $catalog->publicLanguages());

            $catalog->initializePrimary('fr');
            self::assertSame('fr', $settings->get('languages.primary_language'));
            self::assertSame('0', $settings->get('languages.multilingual'));
            self::assertSame(['fr'], array_column($catalog->publicLanguages('fr'), 'iso_code'));
            self::assertTrue($catalog->allForAdministration()[2]['primary'] ||
                count(array_filter($catalog->allForAdministration(),
                    static fn (array $row): bool => $row['primary'])) === 1);
            $catalog->initializePrimary('fr');
            self::assertSame(1, (int) $db->fetchOne(
                'SELECT COUNT(*) FROM symsvel_languages WHERE active = 1',
            ));
        } finally {
            $db->close();
        }
    }

    public function testPreOpeningIsOnlyInBoAndMultilingualControlsPublicList(): void
    {
        [$catalog, $settings, $db] = $this->services();
        try {
            $catalog->initializePrimary('fr');
            $catalog->update('de', false, true);
            $availableCodes = array_column($catalog->availableForAdministration('en'), 'iso_code');
            // API sorts the translated display names, not the ISO codes.
            sort($availableCodes);
            self::assertSame(['de', 'fr'], $availableCodes);
            self::assertSame(['fr'], array_column($catalog->publicLanguages('en'), 'iso_code'));

            $catalog->update('de', true, false);
            self::assertSame(['fr'], array_column($catalog->publicLanguages('en'), 'iso_code'));

            $settings->set('languages.multilingual', '1');
            $settings->flush();
            $codes = array_column($catalog->publicLanguages('en'), 'iso_code');
            sort($codes);
            self::assertSame(['de', 'fr'], $codes);
        } finally {
            $db->close();
        }
    }

    public function testPrimaryCannotBeDeactivatedOrReassigned(): void
    {
        [$catalog, , $db] = $this->services();
        try {
            $catalog->initializePrimary('fr');
            try {
                $catalog->update('fr', false, false);
                self::fail('Primary language was deactivated.');
            } catch (\DomainException) {
            }
            try {
                $catalog->initializePrimary('de');
                self::fail('Primary language was reassigned.');
            } catch (\DomainException) {
            }
            self::assertSame(['fr'], array_column($catalog->publicLanguages(), 'iso_code'));
        } finally {
            $db->close();
        }
    }

    public function testAccountLanguageUsesPrimaryWhenOnlyOneLanguageIsActive(): void
    {
        [$catalog, , $db] = $this->services();
        try {
            $catalog->initializePrimary('fr');
            self::assertCount(1, $catalog->activeForAccounts());
            self::assertSame('fr', $catalog->resolveAccountLanguage('de'));
            $catalog->update('de', false, true);
            self::assertCount(1, $catalog->activeForAccounts());
            $catalog->update('de', true, false);
            self::assertCount(2, $catalog->activeForAccounts());
            self::assertSame('de', $catalog->resolveAccountLanguage('DE'));
            self::assertSame('fr', $catalog->resolveAccountLanguage(null));
            $this->expectException(\InvalidArgumentException::class);
            $catalog->resolveAccountLanguage('zz');
        } finally {
            $db->close();
        }
    }

    public function testUnknownCodesCannotBeSelectedOrUpdated(): void
    {
        [$catalog, , $db] = $this->services();
        try {
            try {
                $catalog->initializePrimary('zz');
                self::fail('Unknown primary should be rejected.');
            } catch (\InvalidArgumentException) {
            }
            try {
                $catalog->update('zz', true, false);
                self::fail('Unknown language should be rejected.');
            } catch (\InvalidArgumentException) {
            }
        } finally {
            $db->close();
        }
    }

    public function testLanguageCodesAndNativeNamesComeFromXCoreReference(): void
    {
        $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/languages.json'),
            true, 32, JSON_THROW_ON_ERROR);
        self::assertCount(117, $catalog);
        self::assertContains(['code' => 'fr', 'name' => 'français'], $catalog);
        self::assertContains(['code' => 'en', 'name' => 'English'], $catalog);
        self::assertCount(117, array_unique(array_column($catalog, 'code')));
    }

    public function testTranslationLocaleHasPriorityOverUiAndBrowser(): void
    {
        $resolver = new LanguageLocaleResolver();
        $request = Request::create('/apibo/languages?ui_lang=fr&translation_lang=en-US');
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.8');
        self::assertSame('en-us', $resolver->resolve($request));
        $fallback = Request::create('/apiopen/languages');
        $fallback->headers->set('Accept-Language', 'fr-FR,fr;q=0.8');
        self::assertSame('fr-fr', $resolver->resolve($fallback));
    }

    public function testPrimaryDefinitionIsReadOnly(): void
    {
        $definitions = iterator_to_array((new LanguagesSettingDefinitionProvider())->definitions());
        self::assertSame('primary_language', $definitions[0]->key);
        self::assertTrue($definitions[0]->blocking);
        self::assertFalse($definitions[0]->editable);
        self::assertSame('boolean', $definitions[1]->type);
    }
}
