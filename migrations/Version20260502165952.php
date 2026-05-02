<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502165952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix nullable createdBy and update enum/default fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE decisions CHANGE StatutD StatutD ENUM('pending','active','refused') NOT NULL DEFAULT 'pending'");

        $this->addSql("ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE stateProj stateProj ENUM('PENDING','ACCEPTED','REFUSED','ARCHIVED') NOT NULL DEFAULT 'PENDING', CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT 0 NOT NULL");

        // Fix existing NULL or empty values before making createdBy NOT NULL
        $this->addSql("UPDATE `user` SET createdBy = 'system' WHERE createdBy IS NULL OR createdBy = ''");

        // Add NOT NULL + DEFAULT to avoid future insert errors
        $this->addSql("ALTER TABLE `user` CHANGE createdBy createdBy VARCHAR(255) NOT NULL DEFAULT 'system'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE decisions CHANGE StatutD StatutD ENUM('pending', 'active', 'refused') DEFAULT 'pending' NOT NULL");

        $this->addSql("ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT '0' NOT NULL, CHANGE stateProj stateProj ENUM('PENDING', 'ACCEPTED', 'REFUSED', 'ARCHIVED') DEFAULT 'PENDING' NOT NULL, CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT '0' NOT NULL");

        $this->addSql("ALTER TABLE `user` CHANGE createdBy createdBy VARCHAR(255) DEFAULT NULL");
    }
}