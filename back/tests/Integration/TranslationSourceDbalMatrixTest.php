<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Languages\Service\TranslationCsvTransfer;
use SymSvel\Module\Languages\Service\TranslationSourceSynchronizer;

/**
 * Real-engine regression for MySQL 1366: false used to be bound as '' by DBAL.
 * The CI supplies a dedicated, empty PostgreSQL/MySQL/MariaDB database.
 */
final class TranslationSourceDbalMatrixTest extends TestCase
{
    public function testSourceBooleanDefaultsAndCsvCustomizationsOnRealEngine(): void
    {
        $url = getenv('SYMSVEL_LANGUAGES_TEST_DATABASE_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('Set SYMSVEL_LANGUAGES_TEST_DATABASE_URL for real-engine testing.');
        }
        $connection = parse_url($url);
        if (!is_array($connection) || !isset($connection['host'], $connection['user'], $connection['path'])) {
            throw new \\RuntimeException('Invalid test database connection URL.');
        }
        $db = DriverManager::getConnection([
            'driver' => $connection['scheme'] === 'postgresql' ? 'pdo_pgsql' : 'pdo_mysql',
            'host' => $connection['host'],
            'port' => $connection['port'] ?? ($connection['scheme'] === 'postgresql' ? 5432 : 3306),
            'user' => rawurldecode($connection['user']),
            'password' => rawurldecode($connection['pass'] ?? ''),
            'dbname' => ltrim($connection['path'], '/'),
        ]);
        $file = tempnam(sys_get_temp_dir(), 'symsvel-languages-matrix-');
        try {
            $schema = new Schema();
            $languages = $schema->createTable('symsvel_languages');
            $languages->addColumn('iso_code', 'string', ['length' => 20]);
            $languages->setPrimaryKey(['iso_code']);
            foreach (['Version20261117000003', 'Version20261117000004', 'Version20261118000001'] as $version) {
                require_once dirname(__DIR__, 3).'/Migration/'.$version.'.php';
                $class = 'DoctrineMigrations\\'.$version;
                (new \ReflectionClass($class))->newInstanceWithoutConstructor()->up($schema);
            }
            foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
                $db->executeStatement($sql);
            }
            $db->insert('symsvel_languages', ['iso_code' => 'fr']);
            $source = [
                'schema' => 1,
                'entries' => [
                    ['scope' => 'bo', 'module' => 'admins', 'key' => 'invite.title', 'language' => 'fr', 'value' => 'Inviter'],
                    ['scope' => 'bo', 'module' => 'admins', 'key' => 'invite.body', 'language' => 'fr', 'value' => 'Bienvenue'],
                ],
            ];
            file_put_contents($file, json_encode($source, JSON_THROW_ON_ERROR));
            $sync = new TranslationSourceSynchronizer($db);
            self::assertSame(2, $sync->sync($file)['texts_created']);

            $flag = static fn (string $key): int => (int) $db->fetchOne(
                'SELECT customized FROM symsvel_translations WHERE scope = ? AND translation_key = ? AND language = ?',
                ['bo', $key, 'fr'],
            );
            self::assertSame(0, $flag('admins.invite.title'));
            self::assertSame(0, $flag('admins.invite.body'));

            $csv = "scope,key,description,language,value\n"
                ."bo,admins.invite.title,,fr,Invitation personnalisée\n"
                ."bo,admins.extra,,fr,Autre texte personnalisé\n";
            $csvTransfer = new TranslationCsvTransfer($db);
            $preview = $csvTransfer->import($csv);
            self::assertSame(1, $preview['texts_created']);
            self::assertSame(1, $preview['texts_updated']);
            $applied = $csvTransfer->import($csv, true, $preview['sha256']);
            self::assertTrue($applied['applied']);
            self::assertSame(1, $flag('admins.invite.title'));
            self::assertSame(1, $flag('admins.extra'));
            self::assertSame(0, $flag('admins.invite.body'));

            $source['entries'][0]['value'] = 'Valeur source modifiée';
            $source['entries'][1]['value'] = 'Bienvenue modifié';
            file_put_contents($file, json_encode($source, JSON_THROW_ON_ERROR));
            $result = $sync->sync($file);
            self::assertSame(1, $result['texts_updated']);
            self::assertSame('Invitation personnalisée', $db->fetchOne(
                'SELECT text_value FROM symsvel_translations WHERE scope = ? AND translation_key = ? AND language = ?',
                ['bo', 'admins.invite.title', 'fr'],
            ));
            self::assertSame('Bienvenue modifié', $db->fetchOne(
                'SELECT text_value FROM symsvel_translations WHERE scope = ? AND translation_key = ? AND language = ?',
                ['bo', 'admins.invite.body', 'fr'],
            ));
            self::assertSame(0, $flag('admins.invite.body'));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
            $db->close();
        }
    }
}
