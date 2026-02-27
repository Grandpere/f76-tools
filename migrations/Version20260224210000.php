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

final class Version20260224210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_user table for local authentication';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
                id SERIAL NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on postgresql.',
        );

        $this->addSql('DROP TABLE app_user');
    }
}
