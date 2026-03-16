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

final class Version20260316182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy source-specific columns from item table after external source split';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP COLUMN IF EXISTS form_id');
        $this->addSql('ALTER TABLE item DROP COLUMN IF EXISTS editor_id');
        $this->addSql('ALTER TABLE item DROP COLUMN IF EXISTS wiki_url');
        $this->addSql('ALTER TABLE item DROP COLUMN IF EXISTS tradeable');
        $this->addSql('ALTER TABLE item DROP COLUMN IF EXISTS payload');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item ADD COLUMN IF NOT EXISTS form_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD COLUMN IF NOT EXISTS editor_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD COLUMN IF NOT EXISTS wiki_url VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD COLUMN IF NOT EXISTS tradeable BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE item ADD COLUMN IF NOT EXISTS payload JSON DEFAULT NULL');
    }
}
