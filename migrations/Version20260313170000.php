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

final class Version20260313170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add OCR async processing columns to roadmap_snapshot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE roadmap_snapshot ADD ocr_processing_status VARCHAR(16) NOT NULL DEFAULT 'done'");
        $this->addSql('ALTER TABLE roadmap_snapshot ADD ocr_processing_error TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE roadmap_snapshot ADD ocr_preprocess_mode VARCHAR(16) NOT NULL DEFAULT 'none'");
        $this->addSql('CREATE INDEX idx_roadmap_snapshot_ocr_processing_status ON roadmap_snapshot (ocr_processing_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_roadmap_snapshot_ocr_processing_status');
        $this->addSql('ALTER TABLE roadmap_snapshot DROP ocr_preprocess_mode');
        $this->addSql('ALTER TABLE roadmap_snapshot DROP ocr_processing_error');
        $this->addSql('ALTER TABLE roadmap_snapshot DROP ocr_processing_status');
    }
}
