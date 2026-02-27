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

final class Version20260224213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create player table linked to app_user';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE player (
                id SERIAL NOT NULL,
                user_id INT NOT NULL,
                name VARCHAR(120) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_player_user ON player (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_player_user_name ON player (user_id, name)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_PLAYER_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP TABLE player');
    }
}
