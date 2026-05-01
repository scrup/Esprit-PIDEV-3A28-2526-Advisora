<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make swot_item strategie_id non-null to match composition mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('ALTER TABLE swot_item CHANGE strategie_id strategie_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql('ALTER TABLE swot_item CHANGE strategie_id strategie_id INT DEFAULT NULL');
    }
}
