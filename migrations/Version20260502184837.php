<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502184837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ensure the column exists (safe even if a previous run partially applied it).
        $this->addSql(<<<'SQL'
SET @has_uuid_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user'
      AND COLUMN_NAME = 'uuid'
)
SQL);
        $this->addSql("SET @add_uuid_sql := IF(@has_uuid_col = 0, 'ALTER TABLE `user` ADD `uuid` BINARY(16) DEFAULT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_uuid FROM @add_uuid_sql');
        $this->addSql('EXECUTE stmt_add_uuid');
        $this->addSql('DEALLOCATE PREPARE stmt_add_uuid');

        // Backfill UUID values for existing rows (and repair zero UUIDs from failed attempts).
        $this->addSql("UPDATE `user` SET `uuid` = UNHEX(REPLACE(UUID(), '-', '')) WHERE `uuid` IS NULL OR `uuid` = 0x00000000000000000000000000000000");

        // Enforce schema constraints after data is valid.
        $this->addSql('ALTER TABLE `user` MODIFY `uuid` BINARY(16) NOT NULL');

        $this->addSql(<<<'SQL'
SET @has_uuid_unique := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user'
      AND INDEX_NAME = 'UNIQ_8D93D649D17F50A6'
)
SQL);
        $this->addSql("SET @add_uuid_unique_sql := IF(@has_uuid_unique = 0, 'CREATE UNIQUE INDEX `UNIQ_8D93D649D17F50A6` ON `user` (`uuid`)', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_uuid_unique FROM @add_uuid_unique_sql');
        $this->addSql('EXECUTE stmt_add_uuid_unique');
        $this->addSql('DEALLOCATE PREPARE stmt_add_uuid_unique');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
SET @has_uuid_unique := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user'
      AND INDEX_NAME = 'UNIQ_8D93D649D17F50A6'
)
SQL);
        $this->addSql("SET @drop_uuid_unique_sql := IF(@has_uuid_unique > 0, 'DROP INDEX `UNIQ_8D93D649D17F50A6` ON `user`', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_uuid_unique FROM @drop_uuid_unique_sql');
        $this->addSql('EXECUTE stmt_drop_uuid_unique');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_uuid_unique');

        $this->addSql(<<<'SQL'
SET @has_uuid_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user'
      AND COLUMN_NAME = 'uuid'
)
SQL);
        $this->addSql("SET @drop_uuid_col_sql := IF(@has_uuid_col > 0, 'ALTER TABLE `user` DROP COLUMN `uuid`', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_uuid_col FROM @drop_uuid_col_sql');
        $this->addSql('EXECUTE stmt_drop_uuid_col');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_uuid_col');
    }
}
