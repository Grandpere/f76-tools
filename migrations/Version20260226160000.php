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

final class Version20260226160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields on users';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE app_user ADD is_email_verified BOOLEAN DEFAULT TRUE NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD email_verification_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD email_verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('ALTER TABLE app_user DROP is_email_verified');
        $this->addSql('ALTER TABLE app_user DROP email_verification_token_hash');
        $this->addSql('ALTER TABLE app_user DROP email_verification_expires_at');
    }
}
