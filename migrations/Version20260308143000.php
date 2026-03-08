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

final class Version20260308143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes for admin list sorting/filtering and case-insensitive user email search';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('CREATE INDEX idx_admin_audit_log_action_occurred_at ON admin_audit_log (action, occurred_at DESC)');
        $this->addSql('CREATE INDEX idx_admin_audit_log_occurred_at_id ON admin_audit_log (occurred_at DESC, id DESC)');
        $this->addSql('CREATE INDEX idx_contact_message_status_created_at ON contact_message (status, created_at DESC)');
        $this->addSql('CREATE INDEX idx_contact_message_created_at_id ON contact_message (created_at DESC, id DESC)');
        $this->addSql('CREATE INDEX idx_app_user_email_lower ON app_user (LOWER(email))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP INDEX idx_app_user_email_lower');
        $this->addSql('DROP INDEX idx_contact_message_created_at_id');
        $this->addSql('DROP INDEX idx_contact_message_status_created_at');
        $this->addSql('DROP INDEX idx_admin_audit_log_occurred_at_id');
        $this->addSql('DROP INDEX idx_admin_audit_log_action_occurred_at');
    }
}
