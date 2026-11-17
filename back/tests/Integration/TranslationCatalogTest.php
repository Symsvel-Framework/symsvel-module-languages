<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Languages\Service\LanguagesSettingDefinitionProvider;
use SymSvel\Module\Languages\Service\TranslationCatalog;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;

final class TranslationCatalogTest extends TestCase
{
    private function catalog(): array
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite required.');
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname((new \ReflectionClass(Setting::class))->getFileName())], isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($db, $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Setting::class)]);

        $schema = new Schema();
        $languages = $schema->createTable('symsvel_languages');
        $languages->addColumn('iso_code', 'string', ['length' => 20]);
        $languages->addColumn('name', 'string', ['length' => 150]);
        $languages->addColumn('active', 'boolean', ['default' => false]);
        $languages->addColumn('pre_open', 'boolean', ['default' => false]);
        $languages->setPrimaryKey(['iso_code']);
        require_once dirname(__DIR__, 2).'/../Migration/Version20261117000003.php';
        $migration = (new \ReflectionClass(\DoctrineMigrations\Version20261117000003::class))
            ->newInstanceWithoutConstructor();
        $migration->up($schema);
        require_once dirname(__DIR__, 2).'/../Migration/Version20261117000004.php';
        $keysMigration = (new \ReflectionClass(\DoctrineMigrations\Version20261117000004::class))
            ->newInstanceWithoutConstructor();
        $keysMigration->up($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) $db->executeStatement($sql);
        foreach (['fr', 'en', 'de'] as $code) {
            $db->insert('symsvel_languages', [
                'iso_code' => $code, 'name' => $code, 'active' => false, 'pre_open' => false,
            ]);
        }

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(Setting::class)->willReturn($em);
        $settingsRegistry = new SettingRegistry([]);
        $settingsRegistry->registerOwned(new LanguagesSettingDefinitionProvider(), 'languages', new SettingKeyQualifier());
        $settings = new SettingManager($settingsRegistry, new SettingRepository($registry), $em,
            new SettingSecretCipher('translation-test'));
        $catalog = new LanguageCatalog($db, $settings);
        $catalog->initializePrimary('fr');
        $catalog->update('en', false, true);
        return [new TranslationCatalog($db, $catalog, $settings), $catalog, $db];
    }

    public function testEditingExportFallbackIsolationAndDeletion(): void
    {
        [$translations, $languages, $db] = $this->catalog();
        try {
            $translations->save('bo', 'fr', 'auth.signIn', 'Connexion');
            $translations->save('bo', 'fr', 'navigation.home', 'Accueil');
            $translations->save('bo', 'en', 'auth.signIn', 'Sign in');
            $translations->save('app', 'fr', 'auth.signIn', 'Espace client');
            self::assertCount(2, $translations->entries('bo', 'en'));
            self::assertTrue($translations->entries('bo', 'en')[1]['missing_translation']);
            self::assertSame('Accueil', $translations->entries('bo', 'en')[1]['fallback_value']);
            self::assertCount(2, $translations->entries('bo', 'fr'));
            $export = $translations->export('bo', 'en');
            self::assertSame('translations.bo.en.ts', $export['filename']);
            self::assertSame(2, $export['entries']);
            self::assertStringContainsString('"auth": {', $export['content']);
            self::assertStringContainsString('"signIn": "Sign in"', $export['content']);
            self::assertStringContainsString('"navigation": {', $export['content']);
            self::assertStringContainsString('"home": "Accueil"', $export['content']);
            self::assertStringNotContainsString('Espace client', $export['content']);
            self::assertSame($export['content'], $translations->export('bo', 'en')['content']);

            $translations->save('bo', 'en', 'auth.signIn', 'Log in');
            self::assertSame('Log in', $translations->entries('bo', 'en')[0]['value']);
            $translations->delete('bo', 'en', 'auth.signIn');
            self::assertCount(2, $translations->entries('bo', 'en'));
            self::assertSame('', $translations->entries('bo', 'en')[0]['value']);
            self::assertTrue($translations->entries('bo', 'en')[0]['missing_translation']);
            self::assertStringContainsString('Connexion', $translations->export('bo', 'en')['content']);

            $languages->update('en', false, false);
            $this->expectException(\DomainException::class);
            $translations->export('bo', 'en');
        } finally {
            $db->close();
        }
    }

    public function testKeyExistsBeforeAnyTranslationIsSaved(): void
    {
        [$translations, , $db] = $this->catalog();
        try {
            $translations->registerKey('app', 'account.confirm', 'Account confirmation');
            $rows = $translations->entries('app', 'fr');
            self::assertCount(1, $rows);
            self::assertSame('account.confirm', $rows[0]['translation_key']);
            self::assertSame('Account confirmation', $rows[0]['description']);
            self::assertTrue($rows[0]['missing_translation']);
            self::assertSame('', $rows[0]['fallback_value']);

            $this->expectException(\DomainException::class);
            $translations->registerKey('app', 'account.confirm');
        } finally {
            $db->close();
        }
    }

    public function testRejectsUnsafeKeysAndScope(): void
    {
        [$translations, , $db] = $this->catalog();
        try {
            $this->expectException(\InvalidArgumentException::class);
            $translations->save('bo', 'fr', '../bad', 'value');
        } finally {
            $db->close();
        }
    }

    public function testRejectsUnsupportedScope(): void
    {
        [$translations, , $db] = $this->catalog();
        try {
            $this->expectException(\InvalidArgumentException::class);
            $translations->export('../backend', 'fr');
        } finally {
            $db->close();
        }
    }
}
