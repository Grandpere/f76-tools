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

final class Version20260224233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dedicated source metadata columns to item and backfill from payload';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE item ADD is_new BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD drop_raid BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD drop_burningsprings BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD drop_dailyops BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD vendor_regs BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD vendor_samuel BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD vendor_mortimer BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD info_html TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD drop_sources_html TEXT DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE item
            SET
                is_new = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'new', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                drop_raid = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'drop_raid', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                drop_burningsprings = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'drop_burningsprings', payload::jsonb ->> 'drop_burningsprings', payload::jsonb ->> 'drop_burning_springs', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                drop_dailyops = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'drop_dailyops', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                vendor_regs = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'vendor_regs', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                vendor_samuel = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'vendor_samuel', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                vendor_mortimer = CASE
                    WHEN lower(coalesce(payload::jsonb ->> 'vendor_mortimer', '')) IN ('1', 'true', 'yes') THEN TRUE
                    ELSE FALSE
                END,
                info_html = nullif(payload::jsonb ->> 'info', ''),
                drop_sources_html = nullif(payload::jsonb ->> 'drop_sources', '')
            WHERE payload IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE item DROP is_new');
        $this->addSql('ALTER TABLE item DROP drop_raid');
        $this->addSql('ALTER TABLE item DROP drop_burningsprings');
        $this->addSql('ALTER TABLE item DROP drop_dailyops');
        $this->addSql('ALTER TABLE item DROP vendor_regs');
        $this->addSql('ALTER TABLE item DROP vendor_samuel');
        $this->addSql('ALTER TABLE item DROP vendor_mortimer');
        $this->addSql('ALTER TABLE item DROP info_html');
        $this->addSql('ALTER TABLE item DROP drop_sources_html');
    }
}
