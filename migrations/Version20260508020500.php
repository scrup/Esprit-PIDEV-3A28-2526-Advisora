<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508020500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cascade user deletion to owned projects and decisions.';
    }

    public function up(Schema $schema): void
    {
        $this->replaceUserFkWithCascade('decision', 'user_id', 'fk_decisions_user');
        $this->replaceUserFkWithCascade('project', 'user_id', 'fk_projects_client');
    }

    public function down(Schema $schema): void
    {
        $this->replaceUserFkWithoutCascade('decision', 'user_id', 'fk_decisions_user');
        $this->replaceUserFkWithoutCascade('project', 'user_id', 'fk_projects_client');
    }

    private function replaceUserFkWithCascade(string $table, string $column, string $constraintName): void
    {
        $this->dropForeignKeysForColumn($table, $column);
        $this->addSql(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `user` (`idUser`) ON DELETE CASCADE',
            $table,
            $constraintName,
            $column
        ));
    }

    private function replaceUserFkWithoutCascade(string $table, string $column, string $constraintName): void
    {
        $this->dropForeignKeysForColumn($table, $column);
        $this->addSql(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `user` (`idUser`)',
            $table,
            $constraintName,
            $column
        ));
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
            $this->addSql(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $foreignKey));
        }
    }
}
