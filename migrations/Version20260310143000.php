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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roadmap season model and link snapshots/canonical events to seasons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE roadmap_season (id SERIAL NOT NULL, season_number INT NOT NULL, title VARCHAR(255) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT FALSE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_roadmap_season_number ON roadmap_season (season_number)');

        $this->addSql('ALTER TABLE roadmap_snapshot ADD season_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_roadmap_snapshot_season_locale_status ON roadmap_snapshot (season_id, locale, status)');
        $this->addSql('CREATE INDEX IDX_C213E80D41F6C15D ON roadmap_snapshot (season_id)');
        $this->addSql('ALTER TABLE roadmap_snapshot ADD CONSTRAINT FK_C213E80D41F6C15D FOREIGN KEY (season_id) REFERENCES roadmap_season (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE roadmap_canonical_event ADD season_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_roadmap_canonical_event_season_starts_sort ON roadmap_canonical_event (season_id, starts_at, sort_order)');
        $this->addSql('CREATE INDEX IDX_40DE498741F6C15D ON roadmap_canonical_event (season_id)');
        $this->addSql('ALTER TABLE roadmap_canonical_event ADD CONSTRAINT FK_40DE498741F6C15D FOREIGN KEY (season_id) REFERENCES roadmap_season (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roadmap_snapshot DROP CONSTRAINT FK_C213E80D41F6C15D');
        $this->addSql('ALTER TABLE roadmap_canonical_event DROP CONSTRAINT FK_40DE498741F6C15D');

        $this->addSql('DROP INDEX IF EXISTS idx_roadmap_snapshot_season_locale_status');
        $this->addSql('DROP INDEX IF EXISTS IDX_C213E80D41F6C15D');
        $this->addSql('DROP INDEX IF EXISTS idx_roadmap_canonical_event_season_starts_sort');
        $this->addSql('DROP INDEX IF EXISTS IDX_40DE498741F6C15D');

        $this->addSql('ALTER TABLE roadmap_snapshot DROP COLUMN season_id');
        $this->addSql('ALTER TABLE roadmap_canonical_event DROP COLUMN season_id');

        $this->addSql('DROP TABLE roadmap_season');
    }
}
