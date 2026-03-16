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

final class Version20260316170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item_external_source table and backfill from item rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS item_external_source (
                    id SERIAL NOT NULL,
                    item_id INT NOT NULL,
                    provider VARCHAR(64) NOT NULL,
                    external_ref VARCHAR(255) NOT NULL,
                    external_url VARCHAR(1024) DEFAULT NULL,
                    metadata JSON DEFAULT NULL,
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_item_external_source_item_provider_ref ON item_external_source (item_id, provider, external_ref)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_item_external_source_provider_ref ON item_external_source (provider, external_ref)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_item_external_source_item_provider ON item_external_source (item_id, provider)');
        $this->addSql('ALTER TABLE item_external_source ADD CONSTRAINT fk_item_external_source_item FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
                INSERT INTO item_external_source (item_id, provider, external_ref, external_url, metadata, created_at, updated_at)
                SELECT i.id,
                       'nukaknights' AS provider,
                       COALESCE(NULLIF(i.form_id, ''), CONCAT('source_id:', i.source_id::text)) AS external_ref,
                       i.wiki_url AS external_url,
                       COALESCE(i.payload, '{}'::json) AS metadata,
                       NOW() AS created_at,
                       NOW() AS updated_at
                FROM item i
                ON CONFLICT (item_id, provider, external_ref) DO UPDATE
                SET external_url = EXCLUDED.external_url,
                    metadata = EXCLUDED.metadata,
                    updated_at = NOW()
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_external_source DROP CONSTRAINT IF EXISTS fk_item_external_source_item');
        $this->addSql('DROP TABLE IF EXISTS item_external_source');
    }
}
