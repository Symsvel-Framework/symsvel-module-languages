<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Stable translation keys, including keys not yet translated in a locale. */
final class Version20261117000004 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('symsvel_translation_keys');
        $table->addColumn('scope', 'string', ['length' => 16]);
        $table->addColumn('translation_key', 'string', ['length' => 190]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->setPrimaryKey(['scope', 'translation_key']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('symsvel_translation_keys');
    }
}
