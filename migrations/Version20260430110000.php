<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align bookings total price storage with decimal money mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql("ALTER TABLE bookings CHANGE totalPrixBk totalPrixBk NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addSql("ALTER TABLE bookings CHANGE totalPrixBk totalPrixBk DOUBLE PRECISION DEFAULT '0' NOT NULL");
    }
}
