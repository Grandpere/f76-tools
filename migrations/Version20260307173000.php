<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create canonical roadmap timeline tables (event + translations).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE roadmap_canonical_event (id SERIAL NOT NULL, translation_key VARCHAR(128) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confidence_score INT NOT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5DD7F6A239D4BE8A ON roadmap_canonical_event (translation_key)');
        $this->addSql('CREATE INDEX idx_roadmap_canonical_event_starts_at ON roadmap_canonical_event (starts_at)');
        $this->addSql('CREATE INDEX idx_roadmap_canonical_event_ends_at ON roadmap_canonical_event (ends_at)');

        $this->addSql('CREATE TABLE roadmap_canonical_event_translation (id SERIAL NOT NULL, event_id INT NOT NULL, locale VARCHAR(8) NOT NULL, title VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A2CBB04771F7E88B ON roadmap_canonical_event_translation (event_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_roadmap_canonical_event_locale ON roadmap_canonical_event_translation (event_id, locale)');
        $this->addSql('ALTER TABLE roadmap_canonical_event_translation ADD CONSTRAINT FK_A2CBB04771F7E88B FOREIGN KEY (event_id) REFERENCES roadmap_canonical_event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roadmap_canonical_event_translation DROP CONSTRAINT FK_A2CBB04771F7E88B');
        $this->addSql('DROP TABLE roadmap_canonical_event_translation');
        $this->addSql('DROP TABLE roadmap_canonical_event');
    }
}

