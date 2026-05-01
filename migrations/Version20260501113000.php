<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize strategy money storage and relation foreign-key column names.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->renameRelationColumn(
            'bookings',
            'idEv',
            'event_id',
            'INT NOT NULL',
            'IDX_7A853C3571F7E88B',
            'FK_7A853C3571F7E88B',
            'events',
            'idEv',
            'CASCADE'
        );
        $this->renameRelationColumn(
            'bookings',
            'idUser',
            'user_id',
            'INT NOT NULL',
            'IDX_7A853C35A76ED395',
            'FK_7A853C35A76ED395',
            'user',
            'idUser',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'decisions',
            'idProj',
            'project_id',
            'INT NOT NULL',
            'IDX_638DAA17166D1F9C',
            'FK_638DAA17166D1F9C',
            'projects',
            'idProj',
            'CASCADE'
        );
        $this->renameRelationColumn(
            'decisions',
            'idUser',
            'user_id',
            'INT NOT NULL',
            'IDX_638DAA17A76ED395',
            'FK_638DAA17A76ED395',
            'user',
            'idUser'
        );

        $this->renameRelationColumn(
            'investments',
            'idProj',
            'project_id',
            'INT NOT NULL',
            'IDX_74FD72E0166D1F9C',
            'FK_74FD72E0166D1F9C',
            'projects',
            'idProj',
            'CASCADE'
        );
        $this->renameRelationColumn(
            'investments',
            'idUser',
            'user_id',
            'INT NOT NULL',
            'IDX_74FD72E0A76ED395',
            'FK_74FD72E0A76ED395',
            'user',
            'idUser',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'objectives',
            'ids',
            'strategie_id',
            'INT NOT NULL',
            'IDX_6CB0696C3AE1B9BD',
            'FK_6CB0696C3AE1B9BD',
            'strategies',
            'idStrategie',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'projects',
            'idClient',
            'user_id',
            'INT NOT NULL',
            'IDX_5C93B3A4A76ED395',
            'FK_5C93B3A4A76ED395',
            'user',
            'idUser',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'strategies',
            'idProj',
            'project_id',
            'INT DEFAULT NULL',
            'IDX_611F2213166D1F9C',
            'FK_611F2213166D1F9C',
            'projects',
            'idProj',
            'CASCADE'
        );
        $this->renameRelationColumn(
            'strategies',
            'idUser',
            'user_id',
            'INT DEFAULT NULL',
            'IDX_611F2213A76ED395',
            'FK_611F2213A76ED395',
            'user',
            'idUser',
            'SET NULL'
        );

        $this->renameRelationColumn(
            'transaction',
            'idInv',
            'investment_id',
            'INT NOT NULL',
            'IDX_723705D16E1B4FD5',
            'FK_723705D16E1B4FD5',
            'investments',
            'idInv',
            'CASCADE'
        );

        if ($this->columnExists('strategies', 'budgetTotal')) {
            $this->addSql('ALTER TABLE strategies CHANGE budgetTotal budgetTotal NUMERIC(10, 2) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        if ($this->columnExists('strategies', 'budgetTotal')) {
            $this->addSql('ALTER TABLE strategies CHANGE budgetTotal budgetTotal DOUBLE DEFAULT NULL');
        }

        $this->renameRelationColumn(
            'transaction',
            'investment_id',
            'idInv',
            'INT NOT NULL',
            'fk_transaction_investment',
            'fk_transaction_investment',
            'investments',
            'idInv',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'strategies',
            'user_id',
            'idUser',
            'INT DEFAULT NULL',
            'fk_strategies_user',
            'fk_strategies_user',
            'user',
            'idUser',
            'SET NULL'
        );
        $this->renameRelationColumn(
            'strategies',
            'project_id',
            'idProj',
            'INT DEFAULT NULL',
            'fk_strategies_project',
            'fk_strategies_project',
            'projects',
            'idProj',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'projects',
            'user_id',
            'idClient',
            'INT NOT NULL',
            'fk_projects_client',
            'fk_projects_client',
            'user',
            'idUser',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'objectives',
            'strategie_id',
            'ids',
            'INT NOT NULL',
            'fk_objectives_strategy',
            'fk_objectives_strategy',
            'strategies',
            'idStrategie',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'investments',
            'user_id',
            'idUser',
            'INT NOT NULL',
            'fk_investments_user',
            'fk_investments_user',
            'user',
            'idUser',
            'CASCADE'
        );
        $this->renameRelationColumn(
            'investments',
            'project_id',
            'idProj',
            'INT NOT NULL',
            'fk_investments_project',
            'fk_investments_project',
            'projects',
            'idProj',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'decisions',
            'user_id',
            'idUser',
            'INT NOT NULL',
            'fk_decisions_user',
            'fk_decisions_user',
            'user',
            'idUser'
        );
        $this->renameRelationColumn(
            'decisions',
            'project_id',
            'idProj',
            'INT NOT NULL',
            'fk_decisions_project',
            'fk_decisions_project',
            'projects',
            'idProj',
            'CASCADE'
        );

        $this->renameRelationColumn(
            'bookings',
            'user_id',
            'idUser',
            'INT NOT NULL',
            'fk_bookings_user',
            'fk_bookings_user',
            'user',
            'idUser',
            'CASCADE'
        );
        $this->renameRelationColumn(
            'bookings',
            'event_id',
            'idEv',
            'INT NOT NULL',
            'fk_bookings_event',
            'fk_bookings_event',
            'events',
            'idEv',
            'CASCADE'
        );
    }

    private function renameRelationColumn(
        string $table,
        string $oldColumn,
        string $newColumn,
        string $columnDefinition,
        string $indexName,
        string $foreignKeyName,
        string $referencedTable,
        string $referencedColumn,
        ?string $onDelete = null
    ): void {
        if (!$this->columnExists($table, $oldColumn) || $this->columnExists($table, $newColumn)) {
            return;
        }

        $this->dropForeignKeysForColumn($table, $oldColumn);
        $this->dropIndexesForColumn($table, $oldColumn);
        $this->addSql(sprintf(
            'ALTER TABLE %s CHANGE %s %s %s',
            $table,
            $oldColumn,
            $newColumn,
            $columnDefinition
        ));
        $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $table, $newColumn));

        $sql = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $table,
            $foreignKeyName,
            $newColumn,
            $referencedTable,
            $referencedColumn
        );

        if ($onDelete !== null) {
            $sql .= sprintf(' ON DELETE %s', $onDelete);
        }

        $this->addSql($sql);
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $foreignKeys = $this->connection->fetchFirstColumn(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($foreignKeys as $foreignKey) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY `%s`', $table, $foreignKey));
        }
    }

    private function dropIndexesForColumn(string $table, string $column): void
    {
        $indexes = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND INDEX_NAME <> \'PRIMARY\'',
            [$table, $column]
        );

        foreach ($indexes as $index) {
            $this->addSql(sprintf('DROP INDEX `%s` ON %s', $index, $table));
        }
    }
}
