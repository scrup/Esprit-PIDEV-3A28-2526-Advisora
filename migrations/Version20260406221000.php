<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406221000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refonte des tables projects et decisions pour le module Project Management.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Cette migration est prevue pour MySQL.');

        $this->addSql('ALTER TABLE projects CHANGE titleProj title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE descriptionProj description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects CHANGE stateProj status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE createdAtProj start_date DATE NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE updatedAtProj end_date DATE NOT NULL');
        $this->addSql('ALTER TABLE projects DROP COLUMN budgetProj');
        $this->addSql('ALTER TABLE projects DROP COLUMN typeProj');
        $this->addSql('ALTER TABLE projects DROP COLUMN avancementProj');

        $this->addSql('ALTER TABLE decisions CHANGE StatutD decision_title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE decisions CHANGE descriptionD description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE decisions CHANGE dateDecision decision_date DATE NOT NULL');
        $this->addSql('ALTER TABLE decisions DROP FOREIGN KEY FK_5FF3132D6B3CA4B');
        $this->addSql('ALTER TABLE decisions DROP COLUMN idUser');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Cette migration est prevue pour MySQL.');

        $this->addSql('ALTER TABLE projects CHANGE title titleProj VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE description descriptionProj LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects CHANGE status stateProj VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE start_date createdAtProj DATETIME NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE end_date updatedAtProj DATETIME NOT NULL');
        $this->addSql('ALTER TABLE projects ADD budgetProj NUMERIC(10, 0) NOT NULL');
        $this->addSql('ALTER TABLE projects ADD typeProj VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD avancementProj NUMERIC(10, 0) NOT NULL');

        $this->addSql('ALTER TABLE decisions CHANGE decision_title StatutD VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE decisions CHANGE description descriptionD LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE decisions CHANGE decision_date dateDecision DATE NOT NULL');
        $this->addSql('ALTER TABLE decisions ADD idUser INT DEFAULT NULL');
        $this->addSql('ALTER TABLE decisions ADD CONSTRAINT FK_5FF3132D6B3CA4B FOREIGN KEY (idUser) REFERENCES user (idUser)');
    }
}
