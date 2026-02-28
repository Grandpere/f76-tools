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

final class Version20260228173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_identity table for OIDC provider identities';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql(<<<'SQL'
                CREATE TABLE user_identity (
                    id SERIAL NOT NULL,
                    user_id INT NOT NULL,
                    provider VARCHAR(32) NOT NULL,
                    provider_user_id VARCHAR(191) NOT NULL,
                    provider_email VARCHAR(180) DEFAULT NULL,
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_identity_provider_external ON user_identity (provider, provider_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_identity_user_provider ON user_identity (user_id, provider)');
        $this->addSql('CREATE INDEX idx_user_identity_user_id ON user_identity (user_id)');
        $this->addSql('ALTER TABLE user_identity ADD CONSTRAINT fk_user_identity_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP TABLE user_identity');
    }
}
