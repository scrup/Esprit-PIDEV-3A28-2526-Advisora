<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit fields (createdBy/updatedBy/updatedAt) and enforce non-null SWOT creation timestamp.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->addColumnIfMissing('user', 'createdBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('user', 'updatedBy', 'VARCHAR(255) DEFAULT NULL');

        $this->addColumnIfMissing('notification', 'updatedAt', 'DATETIME DEFAULT NULL');
        $this->addColumnIfMissing('notification', 'createdBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('notification', 'updatedBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE notification SET updatedAt = createdAt WHERE updatedAt IS NULL');

        $this->addColumnIfMissing('task', 'updated_at', 'DATETIME DEFAULT NULL');
        $this->addColumnIfMissing('task', 'createdBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('task', 'updatedBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE task SET updated_at = created_at WHERE updated_at IS NULL');

        $this->addColumnIfMissing('auth_session', 'updated_at', 'DATETIME DEFAULT NULL');
        $this->addColumnIfMissing('auth_session', 'createdBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('auth_session', 'updatedBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE auth_session SET updated_at = created_at WHERE updated_at IS NULL');

        $this->addColumnIfMissing('swot_item', 'createdBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('swot_item', 'updatedBy', 'VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE swot_item SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE swot_item MODIFY created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prevue pour MySQL.'
        );

        $this->dropColumnIfExists('user', 'createdBy');
        $this->dropColumnIfExists('user', 'updatedBy');

        $this->dropColumnIfExists('notification', 'updatedAt');
        $this->dropColumnIfExists('notification', 'createdBy');
        $this->dropColumnIfExists('notification', 'updatedBy');

        $this->dropColumnIfExists('task', 'updated_at');
        $this->dropColumnIfExists('task', 'createdBy');
        $this->dropColumnIfExists('task', 'updatedBy');

        $this->dropColumnIfExists('auth_session', 'updated_at');
        $this->dropColumnIfExists('auth_session', 'createdBy');
        $this->dropColumnIfExists('auth_session', 'updatedBy');

        $this->dropColumnIfExists('swot_item', 'createdBy');
        $this->dropColumnIfExists('swot_item', 'updatedBy');
        $this->addSql('ALTER TABLE swot_item MODIFY created_at DATETIME DEFAULT NULL');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition));
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column));
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
