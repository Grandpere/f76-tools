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

final class Version20260228223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auth audit log table for security events';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('CREATE TABLE auth_audit_log (id SERIAL NOT NULL, user_id INT DEFAULT NULL, email_hash VARCHAR(64) DEFAULT NULL, level VARCHAR(16) NOT NULL, event VARCHAR(128) NOT NULL, client_ip VARCHAR(45) DEFAULT NULL, context JSON DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_auth_audit_log_user ON auth_audit_log (user_id)');
        $this->addSql('CREATE INDEX idx_auth_audit_log_occurred_at ON auth_audit_log (occurred_at)');
        $this->addSql('CREATE INDEX idx_auth_audit_log_event ON auth_audit_log (event)');
        $this->addSql('ALTER TABLE auth_audit_log ADD CONSTRAINT fk_auth_audit_log_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP TABLE auth_audit_log');
    }
}
