<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source field for minerva rotation generated/manual governance';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql("ALTER TABLE minerva_rotation ADD source VARCHAR(16) DEFAULT 'generated' NOT NULL");
        $this->addSql('CREATE INDEX idx_minerva_rotation_source ON minerva_rotation (source)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP INDEX idx_minerva_rotation_source');
        $this->addSql('ALTER TABLE minerva_rotation DROP source');
    }
}
