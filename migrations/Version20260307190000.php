<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused roadmap_event columns event_type and notes';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE roadmap_event DROP COLUMN event_type');
        $this->addSql('ALTER TABLE roadmap_event DROP COLUMN notes');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE roadmap_event ADD event_type VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE roadmap_event ADD notes TEXT DEFAULT NULL');
    }
}

