<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align notification column defaults with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('ALTER TABLE notification CHANGE eventType eventType VARCHAR(50) NOT NULL, CHANGE createdAt createdAt DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('ALTER TABLE notification CHANGE eventType eventType VARCHAR(50) NOT NULL DEFAULT \'project_created\', CHANGE createdAt createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
}
