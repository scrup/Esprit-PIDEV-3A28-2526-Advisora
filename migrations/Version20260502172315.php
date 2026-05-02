<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502172315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit fields to otp_code/resource_market_* entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE otp_code ADD updated_at DATETIME DEFAULT NULL, ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_market_delivery ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE resource_market_listing ADD createdBy VARCHAR(255) DEFAULT NULL, ADD updatedBy VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE otp_code DROP updated_at, DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE resource_market_delivery DROP createdBy, DROP updatedBy');
        $this->addSql('ALTER TABLE resource_market_listing DROP createdBy, DROP updatedBy');
    }
}
