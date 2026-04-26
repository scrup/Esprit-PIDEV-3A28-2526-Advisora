<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional pricing and map coordinates to events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD price NUMERIC(10, 2) DEFAULT NULL, ADD latitude NUMERIC(10, 8) DEFAULT NULL, ADD longitude NUMERIC(11, 8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP price, DROP latitude, DROP longitude');
    }
}
