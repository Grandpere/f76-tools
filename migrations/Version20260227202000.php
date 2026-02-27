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

final class Version20260227202000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand item_book_list list number constraint from 1..4 to 1..24';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE item_book_list DROP CONSTRAINT IF EXISTS chk_item_book_list_number');
        $this->addSql('ALTER TABLE item_book_list ADD CONSTRAINT chk_item_book_list_number CHECK (list_number BETWEEN 1 AND 24)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DELETE FROM item_book_list WHERE list_number > 4');
        $this->addSql('ALTER TABLE item_book_list DROP CONSTRAINT IF EXISTS chk_item_book_list_number');
        $this->addSql('ALTER TABLE item_book_list ADD CONSTRAINT chk_item_book_list_number CHECK (list_number BETWEEN 1 AND 4)');
    }
}
