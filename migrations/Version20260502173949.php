<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502173949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE decisions CHANGE StatutD StatutD ENUM(\'pending\',\'active\',\'refused\') NOT NULL DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE password_reset DROP FOREIGN KEY `FK_PASSWORD_RESET_UPDATED_BY`');
        $this->addSql('DROP INDEX IDX_B1017252896DBBDE ON password_reset');
        $this->addSql('ALTER TABLE password_reset DROP updated_by_id');
        $this->addSql('ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE stateProj stateProj ENUM(\'PENDING\',\'ACCEPTED\',\'REFUSED\',\'ARCHIVED\') NOT NULL DEFAULT \'PENDING\', CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE decisions CHANGE StatutD StatutD ENUM(\'pending\', \'active\', \'refused\') DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE password_reset ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE password_reset ADD CONSTRAINT `FK_PASSWORD_RESET_UPDATED_BY` FOREIGN KEY (updated_by_id) REFERENCES user (idUser) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B1017252896DBBDE ON password_reset (updated_by_id)');
        $this->addSql('ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE stateProj stateProj ENUM(\'PENDING\', \'ACCEPTED\', \'REFUSED\', \'ARCHIVED\') DEFAULT \'PENDING\' NOT NULL, CHANGE avancementProj avancementProj DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
