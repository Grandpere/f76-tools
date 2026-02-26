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

final class Version20260226200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opaque public IDs for player and item to avoid predictable URLs';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE player ADD public_id VARCHAR(26) DEFAULT NULL');
        $this->addSql("UPDATE player SET public_id = upper(substr(md5(random()::text || clock_timestamp()::text || id::text), 1, 26)) WHERE public_id IS NULL");
        $this->addSql('ALTER TABLE player ALTER COLUMN public_id SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_player_public_id ON player (public_id)');

        $this->addSql('ALTER TABLE item ADD public_id VARCHAR(26) DEFAULT NULL');
        $this->addSql("UPDATE item SET public_id = upper(substr(md5(random()::text || clock_timestamp()::text || id::text), 1, 26)) WHERE public_id IS NULL");
        $this->addSql('ALTER TABLE item ALTER COLUMN public_id SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_public_id ON item (public_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP INDEX uniq_player_public_id');
        $this->addSql('ALTER TABLE player DROP public_id');

        $this->addSql('DROP INDEX uniq_item_public_id');
        $this->addSql('ALTER TABLE item DROP public_id');
    }
}
