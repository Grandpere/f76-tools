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

final class Version20260308123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexed is_admin flag on app_user for fast admin filtering';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE app_user ADD is_admin BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('UPDATE app_user SET is_admin = (roles::jsonb @> \'["ROLE_ADMIN"]\'::jsonb)');
        $this->addSql('CREATE INDEX idx_app_user_is_admin ON app_user (is_admin)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP INDEX idx_app_user_is_admin');
        $this->addSql('ALTER TABLE app_user DROP is_admin');
    }
}
