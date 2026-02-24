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

final class Version20260224150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item table for JSON imports';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE item (
                        id SERIAL NOT NULL,
                        source_id INT NOT NULL,
                        type VARCHAR(16) NOT NULL,
                        name_key VARCHAR(255) NOT NULL,
                        desc_key VARCHAR(255) DEFAULT NULL,
                        form_id VARCHAR(32) DEFAULT NULL,
                        editor_id VARCHAR(255) DEFAULT NULL,
                        rank INT DEFAULT NULL,
                        price INT DEFAULT NULL,
                        price_minerva INT DEFAULT NULL,
                        wiki_url VARCHAR(1024) DEFAULT NULL,
                        tradeable BOOLEAN NOT NULL DEFAULT FALSE,
                        payload JSON DEFAULT NULL,
                        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        PRIMARY KEY(id),
                        CONSTRAINT chk_item_type_rank CHECK (
                            (type = 'MISC' AND rank IS NOT NULL)
                            OR
                            (type = 'BOOK' AND rank IS NULL)
                        )
                    )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE item_book_list (
                        id SERIAL NOT NULL,
                        item_id INT NOT NULL,
                        list_number INT NOT NULL,
                        is_special_list BOOLEAN NOT NULL DEFAULT FALSE,
                        PRIMARY KEY(id),
                        CONSTRAINT chk_item_book_list_number CHECK (list_number BETWEEN 1 AND 4)
                    )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_item_type_source_id ON item (type, source_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_name_key ON item (name_key)');
        $this->addSql('CREATE INDEX idx_item_type_rank ON item (type, rank)');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_book_list ON item_book_list (item_id, list_number)');
        $this->addSql('CREATE INDEX idx_item_book_list_special ON item_book_list (is_special_list)');
        $this->addSql('CREATE INDEX idx_item_book_list_item ON item_book_list (item_id)');
        $this->addSql('ALTER TABLE item_book_list ADD CONSTRAINT FK_ITEM_BOOK_LIST_ITEM FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP TABLE item_book_list');
        $this->addSql('DROP TABLE item');
    }
}
