<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Legacy translations are protected; source-managed defaults are opt-in. */
final class Version20261118000001 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('symsvel_translations')
            ->addColumn('customized', 'boolean', ['default' => true]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('symsvel_translations')->dropColumn('customized');
    }
}
