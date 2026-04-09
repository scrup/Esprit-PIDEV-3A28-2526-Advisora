<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406221000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réconcilie le module projects/decisions avec le schéma legacy conservé comme référence.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Cette migration est prévue pour MySQL.');

        $this->restoreLegacyProjectsColumns();
        $this->restoreLegacyDecisionsColumns();
        $this->cleanupProjectResourcesPivot();
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Cette migration de réconciliation est non réversible en sécurité.');
    }

    private function restoreLegacyProjectsColumns(): void
    {
        if ($this->columnExists('projects', 'title') && !$this->columnExists('projects', 'titleProj')) {
            $this->addSql('ALTER TABLE projects CHANGE title titleProj VARCHAR(160) NOT NULL');
        }

        if ($this->columnExists('projects', 'description') && !$this->columnExists('projects', 'descriptionProj')) {
            $this->addSql('ALTER TABLE projects CHANGE description descriptionProj LONGTEXT DEFAULT NULL');
        }

        if ($this->columnExists('projects', 'status') && !$this->columnExists('projects', 'stateProj')) {
            $this->addSql("ALTER TABLE projects CHANGE status stateProj ENUM('PENDING','ACCEPTED','REFUSED','ARCHIVED') NOT NULL DEFAULT 'PENDING'");
        }

        if ($this->columnExists('projects', 'start_date') && !$this->columnExists('projects', 'createdAtProj')) {
            $this->addSql('ALTER TABLE projects CHANGE start_date createdAtProj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }

        if ($this->columnExists('projects', 'end_date') && !$this->columnExists('projects', 'updatedAtProj')) {
            $this->addSql('ALTER TABLE projects CHANGE end_date updatedAtProj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }

        if (!$this->columnExists('projects', 'budgetProj')) {
            $this->addSql('ALTER TABLE projects ADD budgetProj DOUBLE NOT NULL DEFAULT 0 AFTER descriptionProj');
        }

        if (!$this->columnExists('projects', 'typeProj')) {
            $this->addSql('ALTER TABLE projects ADD typeProj VARCHAR(100) DEFAULT NULL AFTER budgetProj');
        }

        if (!$this->columnExists('projects', 'avancementProj')) {
            $this->addSql('ALTER TABLE projects ADD avancementProj DOUBLE NOT NULL DEFAULT 0 AFTER updatedAtProj');
        }

        $this->addSql("UPDATE projects SET stateProj = 'PENDING' WHERE stateProj IS NULL OR stateProj = ''");
        $this->addSql('ALTER TABLE projects CHANGE titleProj titleProj VARCHAR(160) NOT NULL');
        $this->addSql('ALTER TABLE projects CHANGE descriptionProj descriptionProj LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects CHANGE budgetProj budgetProj DOUBLE NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE projects CHANGE typeProj typeProj VARCHAR(100) DEFAULT NULL');
        $this->addSql("ALTER TABLE projects CHANGE stateProj stateProj ENUM('PENDING','ACCEPTED','REFUSED','ARCHIVED') NOT NULL DEFAULT 'PENDING'");
        $this->addSql('ALTER TABLE projects CHANGE createdAtProj createdAtProj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE projects CHANGE updatedAtProj updatedAtProj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE projects CHANGE avancementProj avancementProj DOUBLE NOT NULL DEFAULT 0');

        if ($this->foreignKeyExists('projects', 'FK_5C93B3A4A455ACCF')) {
            $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4A455ACCF');
        }

        if ($this->indexExists('projects', 'IDX_5C93B3A4A455ACCF')) {
            $this->addSql('ALTER TABLE projects DROP INDEX IDX_5C93B3A4A455ACCF');
        }

        if ($this->indexExists('projects', 'fk_projects_client')) {
            $this->addSql('ALTER TABLE projects ADD INDEX IDX_5C93B3A4A455ACCF (idClient), DROP INDEX fk_projects_client');
        }

        if (!$this->indexExists('projects', 'IDX_5C93B3A4A455ACCF')) {
            $this->addSql('ALTER TABLE projects ADD INDEX IDX_5C93B3A4A455ACCF (idClient)');
        }

        if (!$this->foreignKeyExists('projects', 'fk_projects_client')) {
            $this->addSql('ALTER TABLE projects ADD CONSTRAINT fk_projects_client FOREIGN KEY (idClient) REFERENCES user (idUser) ON DELETE CASCADE');
        }
    }

    private function restoreLegacyDecisionsColumns(): void
    {
        if ($this->columnExists('decisions', 'decision_title') && !$this->columnExists('decisions', 'StatutD')) {
            $this->addSql("ALTER TABLE decisions CHANGE decision_title StatutD ENUM('pending','active','refused') NOT NULL DEFAULT 'pending'");
        }

        if ($this->columnExists('decisions', 'description') && !$this->columnExists('decisions', 'descriptionD')) {
            $this->addSql('ALTER TABLE decisions CHANGE description descriptionD LONGTEXT DEFAULT NULL');
        }

        if ($this->columnExists('decisions', 'decision_date') && !$this->columnExists('decisions', 'dateDecision')) {
            $this->addSql('ALTER TABLE decisions CHANGE decision_date dateDecision DATE NOT NULL');
        }

        if (!$this->columnExists('decisions', 'idUser')) {
            $this->addSql('ALTER TABLE decisions ADD idUser INT DEFAULT NULL AFTER idProj');
            $this->addSql('UPDATE decisions d INNER JOIN projects p ON p.idProj = d.idProj SET d.idUser = p.idClient WHERE d.idUser IS NULL');
            $this->addSql('ALTER TABLE decisions CHANGE idUser idUser INT NOT NULL');
        }

        $this->addSql("UPDATE decisions SET StatutD = 'active' WHERE StatutD IN ('accepted', 'accepté', 'accepte', 'accept')");
        $this->addSql("UPDATE decisions SET StatutD = 'refused' WHERE StatutD IN ('rejected', 'refusé', 'refuse', 'reject')");
        $this->addSql("UPDATE decisions SET StatutD = 'pending' WHERE StatutD IS NULL OR StatutD = ''");
        $this->addSql("ALTER TABLE decisions CHANGE StatutD StatutD ENUM('pending','active','refused') NOT NULL DEFAULT 'pending'");
        $this->addSql('ALTER TABLE decisions CHANGE descriptionD descriptionD LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE decisions CHANGE dateDecision dateDecision DATE NOT NULL');

        if ($this->foreignKeyExists('decisions', 'FK_638DAA1721F1620E')) {
            $this->addSql('ALTER TABLE decisions DROP FOREIGN KEY FK_638DAA1721F1620E');
        }

        if ($this->foreignKeyExists('decisions', 'FK_638DAA17FE6E88D7')) {
            $this->addSql('ALTER TABLE decisions DROP FOREIGN KEY FK_638DAA17FE6E88D7');
        }

        if ($this->indexExists('decisions', 'IDX_638DAA1721F1620E')) {
            $this->addSql('ALTER TABLE decisions DROP INDEX IDX_638DAA1721F1620E');
        }

        if ($this->indexExists('decisions', 'IDX_638DAA17FE6E88D7')) {
            $this->addSql('ALTER TABLE decisions DROP INDEX IDX_638DAA17FE6E88D7');
        }

        if ($this->indexExists('decisions', 'fk_decisions_project')) {
            $this->addSql('ALTER TABLE decisions ADD INDEX IDX_638DAA1721F1620E (idProj), DROP INDEX fk_decisions_project');
        }

        if (!$this->indexExists('decisions', 'IDX_638DAA1721F1620E')) {
            $this->addSql('ALTER TABLE decisions ADD INDEX IDX_638DAA1721F1620E (idProj)');
        }

        if ($this->indexExists('decisions', 'fk_decisions_user')) {
            $this->addSql('ALTER TABLE decisions ADD INDEX IDX_638DAA17FE6E88D7 (idUser), DROP INDEX fk_decisions_user');
        }

        if (!$this->indexExists('decisions', 'IDX_638DAA17FE6E88D7')) {
            $this->addSql('ALTER TABLE decisions ADD INDEX IDX_638DAA17FE6E88D7 (idUser)');
        }

        if (!$this->foreignKeyExists('decisions', 'fk_decisions_project')) {
            $this->addSql('ALTER TABLE decisions ADD CONSTRAINT fk_decisions_project FOREIGN KEY (idProj) REFERENCES projects (idProj) ON DELETE CASCADE');
        }

        if (!$this->foreignKeyExists('decisions', 'fk_decisions_user')) {
            $this->addSql('ALTER TABLE decisions ADD CONSTRAINT fk_decisions_user FOREIGN KEY (idUser) REFERENCES user (idUser)');
        }
    }

    private function cleanupProjectResourcesPivot(): void
    {
        if ($this->columnExists('project_resources', 'qtyAllocated')) {
            $this->addSql('ALTER TABLE project_resources DROP COLUMN qtyAllocated');
        }

        if ($this->foreignKeyExists('project_resources', 'FK_FE5AAE7921F1620E')) {
            $this->addSql('ALTER TABLE project_resources DROP FOREIGN KEY FK_FE5AAE7921F1620E');
        }

        if ($this->foreignKeyExists('project_resources', 'FK_FE5AAE7969351B39')) {
            $this->addSql('ALTER TABLE project_resources DROP FOREIGN KEY FK_FE5AAE7969351B39');
        }

        if ($this->indexExists('project_resources', 'IDX_FE5AAE7969351B39')) {
            $this->addSql('ALTER TABLE project_resources DROP INDEX IDX_FE5AAE7969351B39');
        }

        if ($this->indexExists('project_resources', 'fk_prjres_resource')) {
            $this->addSql('ALTER TABLE project_resources ADD INDEX IDX_FE5AAE7969351B39 (idRs), DROP INDEX fk_prjres_resource');
        }

        if (!$this->indexExists('project_resources', 'IDX_FE5AAE7969351B39')) {
            $this->addSql('ALTER TABLE project_resources ADD INDEX IDX_FE5AAE7969351B39 (idRs)');
        }

        if (!$this->foreignKeyExists('project_resources', 'fk_prjres_project')) {
            $this->addSql('ALTER TABLE project_resources ADD CONSTRAINT fk_prjres_project FOREIGN KEY (idProj) REFERENCES projects (idProj) ON DELETE CASCADE');
        }

        if (!$this->foreignKeyExists('project_resources', 'fk_prjres_resource')) {
            $this->addSql('ALTER TABLE project_resources ADD CONSTRAINT fk_prjres_resource FOREIGN KEY (idRs) REFERENCES resources (idRs) ON DELETE CASCADE');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $foreignKey, 'FOREIGN KEY']
        ) > 0;
    }
}
