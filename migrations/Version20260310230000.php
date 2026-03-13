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

final class Version20260310230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist OCR provider attempt summary on roadmap snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roadmap_snapshot ADD ocr_attempts_summary TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roadmap_snapshot DROP ocr_attempts_summary');
    }
}
