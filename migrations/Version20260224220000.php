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

final class Version20260224220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create player_item_knowledge table linked to player and item';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE player_item_knowledge (
                id SERIAL NOT NULL,
                player_id INT NOT NULL,
                item_id INT NOT NULL,
                learned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_player_item_knowledge ON player_item_knowledge (player_id, item_id)');
        $this->addSql('CREATE INDEX idx_player_item_knowledge_player ON player_item_knowledge (player_id)');
        $this->addSql('CREATE INDEX idx_player_item_knowledge_item ON player_item_knowledge (item_id)');
        $this->addSql('ALTER TABLE player_item_knowledge ADD CONSTRAINT FK_PLAYER_ITEM_KNOWLEDGE_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_item_knowledge ADD CONSTRAINT FK_PLAYER_ITEM_KNOWLEDGE_ITEM FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP TABLE player_item_knowledge');
    }
}
