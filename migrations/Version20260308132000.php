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

final class Version20260308132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for admin users filtering and identity join';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('CREATE INDEX idx_app_user_created_at ON app_user (created_at)');
        $this->addSql('CREATE INDEX idx_app_user_is_active ON app_user (is_active)');
        $this->addSql('CREATE INDEX idx_app_user_is_email_verified ON app_user (is_email_verified)');
        $this->addSql('CREATE INDEX idx_app_user_has_local_password ON app_user (has_local_password)');
        $this->addSql('CREATE INDEX idx_user_identity_provider_user_id ON user_identity (provider, user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP INDEX idx_user_identity_provider_user_id');
        $this->addSql('DROP INDEX idx_app_user_has_local_password');
        $this->addSql('DROP INDEX idx_app_user_is_email_verified');
        $this->addSql('DROP INDEX idx_app_user_is_active');
        $this->addSql('DROP INDEX idx_app_user_created_at');
    }
}
