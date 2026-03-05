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

final class Version20260305173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add drop_bigfoot and note_key columns on item with payload backfill';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE item ADD drop_bigfoot BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD note_key VARCHAR(255) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE item
            SET
                drop_bigfoot = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'drop_bigfoot', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                note_key = CASE
                    WHEN nullif(coalesce(payload::jsonb ->> 'note_en', payload::jsonb ->> 'note_de', ''), '') IS NOT NULL
                        THEN 'item.' || lower(type) || '.' || source_id || '.note'
                    ELSE NULL
                END
            WHERE payload IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE item DROP drop_bigfoot');
        $this->addSql('ALTER TABLE item DROP note_key');
    }
}
