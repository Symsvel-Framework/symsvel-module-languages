<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Owns the per-interface, per-language translation values edited from the BO. */
final class Version20261117000003 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('symsvel_translations');
        $table->addColumn('scope', 'string', ['length' => 16]);
        $table->addColumn('translation_key', 'string', ['length' => 190]);
        $table->addColumn('language', 'string', ['length' => 4]);
        $table->addColumn('value', 'text');
        $table->setPrimaryKey(['scope', 'translation_key', 'language']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('symsvel_translations');
    }
}
