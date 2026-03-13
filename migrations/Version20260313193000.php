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

final class Version20260313193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages table for Doctrine Messenger transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS messenger_messages (
                    id BIGSERIAL NOT NULL,
                    body TEXT NOT NULL,
                    headers TEXT NOT NULL,
                    queue_name VARCHAR(190) NOT NULL,
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_messenger_messages_queue_available_delivered_id ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
