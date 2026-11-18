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
use SymSvel\Module\Languages\Service\TranslationCsvTransfer;
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
        require_once dirname(__DIR__, 2).'/../Migration/Version20261118000001.php';
        $sourceMigration = (new \ReflectionClass(\DoctrineMigrations\Version20261118000001::class))
            ->newInstanceWithoutConstructor();
        $sourceMigration->up($schema);
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
            self::assertCount(0, $translations->entries('bo', 'en', 'admins'));
            self::assertCount(1, $translations->entries('bo', 'en', 'auth'));
            $stats = $translations->moduleStatistics('en', ['auth', 'admins']);
            self::assertSame(2, $stats['total']);
            self::assertSame(1, $stats['translated']);
            self::assertSame(1, $stats['missing']);
            self::assertSame(50, $stats['percent']);
            self::assertNull($stats['modules'][2]['percent']);
            self::assertSame(['bo', 'app'], $stats['modules'][1]['scopes']);
            self::assertSame([], $stats['modules'][2]['scopes']);
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

    public function testSourceSyncIsIdempotentAndPreservesBoCustomizations(): void
    {
        [$translations, , $db] = $this->catalog();
        $file = tempnam(sys_get_temp_dir(), 'symsvel-i18n-source-');
        try {
            $payload = ['schema' => 1, 'entries' => [
                ['scope' => 'bo', 'module' => 'admins', 'key' => 'invite.title', 'language' => 'fr', 'value' => 'Inviter'],
                ['scope' => 'bo', 'module' => 'admins', 'key' => 'invite.title', 'language' => 'en', 'value' => 'Invite'],
            ]];
            file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
            $sync = new \SymSvel\Module\Languages\Service\TranslationSourceSynchronizer($db);
            self::assertSame(2, $sync->sync($file)['texts_created']);
            self::assertSame(0, $sync->sync($file)['texts_created']);
            self::assertSame(0, $sync->sync($file)['texts_updated']);
            $translations->save('bo', 'fr', 'admins.invite.title', 'Invitation personnalisée');
            $payload['entries'][0]['value'] = 'Nouvelle valeur source';
            $payload['entries'][1]['value'] = 'Updated invite';
            file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
            $result = $sync->sync($file);
            self::assertSame(1, $result['texts_updated']);
            self::assertSame('Invitation personnalisée', $translations->entries('bo', 'fr')[0]['value']);
            self::assertSame('Updated invite', $translations->entries('bo', 'en')[0]['value']);
        } finally {
            if (is_file($file)) unlink($file);
            $db->close();
        }
    }

    public function testLegacyBoComponentsCustomizationsSurviveSystemNamespace(): void
    {
        [$translations, , $db] = $this->catalog();
        $file = tempnam(sys_get_temp_dir(), 'symsvel-i18n-legacy-');
        try {
            $translations->save('bo', 'fr', 'components.first', 'Revenir au début');
            file_put_contents($file, json_encode(['schema' => 1, 'entries' => [[
                'scope' => 'bo', 'module' => 'system', 'key' => 'first',
                'language' => 'fr', 'value' => 'Première page',
            ]]], JSON_THROW_ON_ERROR));
            $sync = new \\SymSvel\\Module\\Languages\\Service\\TranslationSourceSynchronizer($db);
            $result = $sync->sync($file);
            self::assertSame(1, $result['protected']);
            self::assertSame('Revenir au début', $translations->entries('bo', 'fr', 'system')[0]['value']);
            self::assertSame(0, $sync->sync($file)['texts_updated']);
            self::assertSame('Revenir au début', $translations->entries('bo', 'fr', 'components')[0]['value']);
        } finally {
            if (is_file($file)) unlink($file);
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

    public function testCsvPreviewApplyAndNonDestructiveRoundTrip(): void
    {
        [$translations, , $db] = $this->catalog();
        try {
            $csv = new TranslationCsvTransfer($db);
            $translations->save('bo', 'fr', 'auth.signIn', 'Connexion');
            $translations->save('bo', 'en', 'auth.signIn', 'Sign in');
            $translations->registerKey('app', 'profile.title', 'Profile heading');
            $export = $csv->export();
            self::assertSame(3, $export['rows']);
            self::assertStringContainsString('scope,key,description,language,value', $export['content']);
            self::assertStringContainsString('app,profile.title,"Profile heading",,', $export['content']);

            $incoming = "scope,key,description,language,value\n"
                ."bo,auth.signIn,,en,Log in\n"
                ."bo,auth.signIn,,fr,\n"
                ."bo,auth.signUp,,fr,Créer un compte\n"
                ."bo,auth.signUp,,en,Sign up\n";
            $preview = $csv->import($incoming);
            self::assertFalse($preview['applied']);
            self::assertSame(0, $preview['keys_updated']);
            self::assertSame(1, $preview['keys_created']);
            self::assertSame(2, $preview['texts_created']);
            self::assertSame(1, $preview['texts_updated']);
            self::assertSame('Sign in', $translations->entries('bo', 'en')[0]['value']);

            $applied = $csv->import($incoming, true, $preview['sha256']);
            self::assertTrue($applied['applied']);
            self::assertSame('Log in', $translations->entries('bo', 'en')[0]['value']);
            self::assertSame('Connexion', $translations->entries('bo', 'fr')[0]['value']);
            self::assertCount(1, $translations->entries('app', 'fr'));
            $again = $csv->import($incoming);
            self::assertSame(0, $again['texts_updated']);
            self::assertSame(0, $again['texts_created']);
        } finally {
            $db->close();
        }
    }

    public function testCsvRejectsDuplicateAndPreservesDatabaseOnError(): void
    {
        [$translations, , $db] = $this->catalog();
        try {
            $csv = new TranslationCsvTransfer($db);
            $translations->save('bo', 'fr', 'test.label', 'Original');
            $incoming = "scope,key,description,language,value\n"
                ."bo,test.label,,fr,Changed\n"
                ."bo,test.label,,fr,Another\n";
            try {
                $csv->import($incoming, true, hash('sha256', $incoming));
                self::fail('Duplicate CSV row must be rejected.');
            } catch (\InvalidArgumentException) {
                self::assertSame('Original', $translations->entries('bo', 'fr')[0]['value']);
            }
            $this->expectException(\InvalidArgumentException::class);
            $csv->import("scope,key,description,language,value\nbo,test.label,,zz,Unknown\n");
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
