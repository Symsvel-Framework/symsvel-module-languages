<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Database-independent Schema migration, compiled by SymSvel Tooling. */
final class Version20261116192000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('symsvel_languages');
        $table->addColumn('iso_code', 'string', ['length' => 20]);
        $table->addColumn('name', 'string', ['length' => 150]);
        $table->addColumn('active', 'boolean', ['default' => false]);
        $table->addColumn('pre_open', 'boolean', ['default' => false]);
        $table->setPrimaryKey(['iso_code']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('symsvel_languages');
    }
}
