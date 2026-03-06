<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roadmap snapshot/event persistence tables for OCR workflow';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE roadmap_snapshot (
                id SERIAL NOT NULL,
                approved_by_user_id INT DEFAULT NULL,
                locale VARCHAR(8) NOT NULL,
                source_image_path VARCHAR(1024) NOT NULL,
                source_image_hash VARCHAR(64) NOT NULL,
                ocr_provider VARCHAR(32) NOT NULL,
                ocr_confidence DOUBLE PRECISION NOT NULL,
                raw_text TEXT NOT NULL,
                status VARCHAR(16) NOT NULL,
                scanned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_roadmap_snapshot_locale_status ON roadmap_snapshot (locale, status)');
        $this->addSql('CREATE INDEX idx_roadmap_snapshot_scanned_at ON roadmap_snapshot (scanned_at)');
        $this->addSql('CREATE INDEX IDX_C213E80DBD6430DF ON roadmap_snapshot (approved_by_user_id)');
        $this->addSql('ALTER TABLE roadmap_snapshot ADD CONSTRAINT FK_C213E80DBD6430DF FOREIGN KEY (approved_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE roadmap_event (
                id SERIAL NOT NULL,
                snapshot_id INT NOT NULL,
                locale VARCHAR(8) NOT NULL,
                title VARCHAR(255) NOT NULL,
                event_type VARCHAR(64) DEFAULT NULL,
                starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                notes TEXT DEFAULT NULL,
                sort_order INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_roadmap_event_snapshot ON roadmap_event (snapshot_id)');
        $this->addSql('CREATE INDEX idx_roadmap_event_locale_starts_at ON roadmap_event (locale, starts_at)');
        $this->addSql('CREATE INDEX idx_roadmap_event_locale_ends_at ON roadmap_event (locale, ends_at)');
        $this->addSql('ALTER TABLE roadmap_event ADD CONSTRAINT FK_8D4D6582613FECDF FOREIGN KEY (snapshot_id) REFERENCES roadmap_snapshot (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE roadmap_event DROP CONSTRAINT FK_8D4D6582613FECDF');
        $this->addSql('ALTER TABLE roadmap_snapshot DROP CONSTRAINT FK_C213E80DBD6430DF');
        $this->addSql('DROP TABLE roadmap_event');
        $this->addSql('DROP TABLE roadmap_snapshot');
    }
}

